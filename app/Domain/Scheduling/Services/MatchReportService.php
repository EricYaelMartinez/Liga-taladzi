<?php

namespace App\Domain\Scheduling\Services;

use App\Domain\Identity\Models\User;
use App\Domain\Player\Models\PlayerRegistration;
use App\Domain\Scheduling\Enums\MatchReportStatus;
use App\Domain\Scheduling\Models\GameMatch;
use App\Domain\Scheduling\Models\MatchReport;
use App\Domain\Scheduling\Models\MatchReportConfirmation;
use App\Domain\Team\Models\TeamRepresentative;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class MatchReportService
{
    public function save(GameMatch $match, array $data, User $actor): MatchReport
    {
        $this->assertScoreAndEvents($match, $data);

        return DB::transaction(function () use ($match, $data, $actor): MatchReport {
            GameMatch::whereKey($match->id)->lockForUpdate()->firstOrFail();
            $report = MatchReport::where('match_id', $match->id)->lockForUpdate()->first();
            if ($report && ! in_array($report->status, [MatchReportStatus::Draft, MatchReportStatus::CorrectionRequired], true)) {
                throw ValidationException::withMessages(['report' => 'La cédula está bloqueada y ya no admite modificaciones.']);
            }
            $report ??= MatchReport::create(['match_id' => $match->id, 'status' => MatchReportStatus::Draft, 'created_by' => $actor->id]);
            $report->update([
                'home_score' => $data['home_score'], 'away_score' => $data['away_score'],
                'home_no_show' => $data['home_no_show'], 'away_no_show' => $data['away_no_show'],
                'summary' => $data['summary'] ?? null, 'incidents' => $data['incidents'] ?? null,
            ]);
            $report->events()->delete();
            foreach ($data['events'] as $event) {
                $report->events()->create([...$event, 'created_by' => $actor->id]);
            }
            return $report->fresh()->load($this->relations());
        });
    }

    public function submit(GameMatch $match, User $actor, int $leagueId): MatchReport
    {
        return DB::transaction(function () use ($match, $actor, $leagueId): MatchReport {
            GameMatch::whereKey($match->id)->lockForUpdate()->firstOrFail();
            $report = MatchReport::where('match_id', $match->id)->lockForUpdate()->first();
            if (! $report || ! in_array($report->status, [MatchReportStatus::Draft, MatchReportStatus::CorrectionRequired], true)) {
                throw ValidationException::withMessages(['report' => 'La cédula no está disponible para envío.']);
            }
            $representatives = TeamRepresentative::query()->where('status', 'active')
                ->whereIn('team_id', [$match->homeParticipation->team_id, $match->awayParticipation->team_id])
                ->orderByDesc('started_at')->get()->unique('team_id')->keyBy('team_id');
            if (! $representatives->has($match->homeParticipation->team_id) || ! $representatives->has($match->awayParticipation->team_id)) {
                throw ValidationException::withMessages(['report' => 'Ambos equipos deben tener un representante activo antes de enviar la cédula.']);
            }
            $report->confirmations()->delete();
            foreach ([$match->homeParticipation, $match->awayParticipation] as $participation) {
                $report->confirmations()->create([
                    'team_participation_id' => $participation->id,
                    'representative_user_id' => $representatives[$participation->team_id]->user_id,
                    'decision' => 'pending',
                ]);
            }
            $report->update([
                'folio' => $report->folio ?: sprintf('CED-%04d-%08d', $leagueId, $match->id),
                'status' => MatchReportStatus::AwaitingConfirmations,
                'submitted_by' => $actor->id, 'submitted_at' => now(),
                'reviewed_by' => null, 'reviewed_at' => null, 'review_notes' => null,
            ]);
            return $report->fresh()->load($this->relations());
        });
    }

    public function confirm(MatchReport $report, MatchReportConfirmation $confirmation, string $decision, ?string $notes, User $actor): MatchReport
    {
        return DB::transaction(function () use ($report, $confirmation, $decision, $notes, $actor): MatchReport {
            $locked = MatchReport::whereKey($report->id)->lockForUpdate()->firstOrFail();
            if ($locked->status !== MatchReportStatus::AwaitingConfirmations) {
                throw ValidationException::withMessages(['report' => 'La cédula ya no está esperando conformidad.']);
            }
            $lockedConfirmation = MatchReportConfirmation::whereKey($confirmation->id)->lockForUpdate()->firstOrFail();
            if ($lockedConfirmation->representative_user_id !== $actor->id) abort(403);
            $lockedConfirmation->update(['decision' => $decision, 'notes' => $notes, 'decided_at' => now()]);
            if ($decision === 'rejected') {
                $locked->update(['status' => MatchReportStatus::CorrectionRequired, 'review_notes' => $notes ?: 'Un representante solicitó correcciones.']);
            } elseif (! $locked->confirmations()->where('decision', '!=', 'confirmed')->exists()) {
                $locked->update(['status' => MatchReportStatus::UnderReview]);
            }
            return $locked->fresh()->load($this->relations());
        });
    }

    public function review(MatchReport $report, string $action, string $notes, User $actor): MatchReport
    {
        return DB::transaction(function () use ($report, $action, $notes, $actor): MatchReport {
            $locked = MatchReport::whereKey($report->id)->lockForUpdate()->firstOrFail();
            if ($locked->status !== MatchReportStatus::UnderReview) {
                throw ValidationException::withMessages(['report' => 'La cédula todavía no está lista para revisión administrativa.']);
            }
            if ($action === 'approve') {
                $locked->update(['status' => MatchReportStatus::Approved, 'reviewed_by' => $actor->id, 'reviewed_at' => now(), 'review_notes' => $notes, 'approved_at' => now()]);
                $locked->match()->update(['status' => 'finished']);
            } else {
                $locked->update(['status' => MatchReportStatus::CorrectionRequired, 'reviewed_by' => $actor->id, 'reviewed_at' => now(), 'review_notes' => $notes, 'approved_at' => null]);
                $locked->confirmations()->update(['decision' => 'pending', 'notes' => null, 'decided_at' => null]);
            }
            return $locked->fresh()->load($this->relations());
        });
    }

    private function assertScoreAndEvents(GameMatch $match, array $data): void
    {
        if ($data['home_no_show'] && $data['away_no_show']) {
            throw ValidationException::withMessages(['report' => 'No pueden marcarse ambos equipos como incomparecientes.']);
        }
        $teamIds = [$match->home_team_participation_id, $match->away_team_participation_id];
        $registrationIds = collect($data['events'])->flatMap(fn (array $event) => [$event['player_registration_id'] ?? null, $event['related_player_registration_id'] ?? null])->filter()->unique()->values();
        $submittedLineups = $match->lineups()->where('status', 'submitted')->with('players:id,match_lineup_id,player_registration_id')->get()->keyBy('team_participation_id');
        $eligibleIds = collect($teamIds)->flatMap(function (int $teamId) use ($submittedLineups) {
            $lineup = $submittedLineups->get($teamId);
            return $lineup
                ? $lineup->players->pluck('player_registration_id')
                : PlayerRegistration::where('team_participation_id', $teamId)->where('status', 'active')->whereHas('player', fn ($query) => $query->where('status', 'active'))->pluck('id');
        })->unique();
        $registrations = PlayerRegistration::whereIn('id', $registrationIds)->whereIn('id', $eligibleIds)->whereIn('team_participation_id', $teamIds)
            ->where('status', 'active')->whereHas('player', fn ($query) => $query->where('status', 'active'))->get()->keyBy('id');
        if ($registrations->count() !== $registrationIds->count()) {
            throw ValidationException::withMessages(['events' => 'Un evento contiene un jugador que no pertenece a este partido.']);
        }
        $homeGoals = 0; $awayGoals = 0;
        foreach ($data['events'] as $event) {
            $teamId = $event['team_participation_id'] ?? null;
            if ($teamId && ! in_array((int) $teamId, $teamIds, true)) throw ValidationException::withMessages(['events' => 'El equipo de un evento no participa en este partido.']);
            if ($event['type'] !== 'incident' && ! $teamId) throw ValidationException::withMessages(['events' => 'Selecciona el equipo correspondiente al evento.']);
            $player = isset($event['player_registration_id']) ? $registrations->get((int) $event['player_registration_id']) : null;
            if ($player && $teamId && $player->team_participation_id !== (int) $teamId) throw ValidationException::withMessages(['events' => 'El jugador no pertenece al equipo indicado en el evento.']);
            if ($event['type'] !== 'incident' && ! $player) throw ValidationException::withMessages(['events' => 'Selecciona un jugador para cada evento deportivo.']);
            if ($event['type'] === 'substitution') {
                $related = isset($event['related_player_registration_id']) ? $registrations->get((int) $event['related_player_registration_id']) : null;
                if (! $related || $related->team_participation_id !== (int) $teamId || $related->id === $player?->id) throw ValidationException::withMessages(['events' => 'La sustitución debe incluir dos jugadores distintos del mismo equipo.']);
            }
            if ($event['type'] === 'goal') $teamId === $match->home_team_participation_id ? $homeGoals++ : $awayGoals++;
            if ($event['type'] === 'own_goal') $teamId === $match->home_team_participation_id ? $awayGoals++ : $homeGoals++;
        }
        if (! $data['home_no_show'] && ! $data['away_no_show'] && ($homeGoals !== (int) $data['home_score'] || $awayGoals !== (int) $data['away_score'])) {
            throw ValidationException::withMessages(['report' => 'El marcador debe coincidir con los goles y autogoles registrados.']);
        }
    }

    private function relations(): array
    {
        return ['events.playerRegistration.player:id,full_name', 'events.relatedPlayerRegistration.player:id,full_name', 'events.teamParticipation.team:id,name', 'confirmations.representative:id,name', 'confirmations.teamParticipation.team:id,name'];
    }
}
