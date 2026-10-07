<?php

namespace App\Domain\Scheduling\Services;

use App\Domain\Identity\Models\User;
use App\Domain\Referee\Models\Referee;
use App\Domain\Referee\Services\RefereeAvailabilityService;
use App\Domain\Scheduling\Models\GameMatch;
use App\Domain\Scheduling\Models\PlayingField;
use App\Domain\Team\Models\TeamParticipation;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class MatchSchedulingService
{
    public function __construct(
        private readonly FieldAvailabilityService $fields,
        private readonly RefereeAvailabilityService $referees,
    ) {}

    public function schedule(GameMatch $match, array $data, User $actor): GameMatch
    {
        $match->loadMissing('competition.tournament.season', 'competition.regulation', 'matchday', 'refereeAssignments');
        if (! in_array($match->status->value, ['draft', 'scheduled', 'postponed', 'suspended'], true)) {
            throw ValidationException::withMessages(['scheduled_at' => 'Este partido ya no admite cambios de programación.']);
        }

        $startsAt = Carbon::parse($data['scheduled_at']);
        $endsAt = $startsAt->copy()->addMinutes($match->duration_minutes);
        $buffer = (int) ($match->competition->regulation->league_settings_snapshot['schedule_buffer_minutes'] ?? 0);
        $reservedUntil = $endsAt->copy()->addMinutes($buffer);
        $leagueId = $match->competition->tournament->season->league_id;
        $field = PlayingField::whereHas('venue', fn ($query) => $query->where('league_id', $leagueId))->findOrFail($data['playing_field_id']);
        $refereeIds = collect($data['referees'])->filter()->values();
        if ($refereeIds->count() !== $refereeIds->unique()->count()) {
            throw ValidationException::withMessages(['central_referee_id' => 'Un árbitro no puede ocupar más de una función en el mismo partido.']);
        }
        $selectedReferees = Referee::where('league_id', $leagueId)->whereIn('id', $refereeIds)->get()->keyBy('id');
        if ($selectedReferees->count() !== $refereeIds->count()) {
            throw ValidationException::withMessages(['central_referee_id' => 'Uno de los árbitros no pertenece a la liga activa.']);
        }
        if ($match->matchday->phase->value !== 'knockout' && $refereeIds->count() > 1) {
            throw ValidationException::withMessages(['assistant_1_referee_id' => 'Los asistentes y cuarto árbitro solo pueden asignarse en fase eliminatoria.']);
        }
        if ($match->status->value === 'suspended') {
            $current = $match->refereeAssignments->pluck('referee_id')->sort()->values()->all();
            $requested = $refereeIds->sort()->values()->all();
            if ($current !== $requested) throw ValidationException::withMessages(['central_referee_id' => 'No se permite reemplazar árbitros después de iniciado el partido.']);
        }

        return DB::transaction(function () use ($match, $data, $actor, $field, $selectedReferees, $startsAt, $endsAt, $reservedUntil, $buffer): GameMatch {
            PlayingField::whereKey($field->id)->lockForUpdate()->firstOrFail();
            TeamParticipation::whereIn('id', [$match->home_team_participation_id, $match->away_team_participation_id])->orderBy('id')->lockForUpdate()->get();
            Referee::whereIn('id', $selectedReferees->keys())->orderBy('id')->lockForUpdate()->get();
            $lockedMatch = GameMatch::whereKey($match->id)->lockForUpdate()->firstOrFail();

            if (! $this->fields->isAvailable($field, $startsAt, $endsAt, $buffer)) {
                throw ValidationException::withMessages(['playing_field_id' => 'La cancha no está disponible durante todo el partido y su margen configurado.']);
            }
            foreach ($selectedReferees as $referee) {
                if (! $this->referees->isAvailable($referee, $startsAt, $reservedUntil)) {
                    throw ValidationException::withMessages(['central_referee_id' => "{$referee->user->name} no está disponible en este horario."]);
                }
            }

            $this->assertNoFieldConflict($lockedMatch, $field->id, $startsAt, $reservedUntil, $buffer);
            $this->assertNoTeamConflict($lockedMatch, $startsAt, $reservedUntil, $buffer);
            $this->assertNoRefereeConflict($lockedMatch, $selectedReferees->keys()->all(), $startsAt, $reservedUntil, $buffer);

            $old = $this->snapshot($lockedMatch->load('refereeAssignments'));
            $lockedMatch->update([
                'playing_field_id' => $field->id,
                'scheduled_at' => $startsAt,
                'scheduled_end_at' => $endsAt,
                'public_notes' => $data['public_notes'] ?? null,
                'status' => $lockedMatch->matchday->status->value === 'published' ? 'scheduled' : 'draft',
            ]);
            $lockedMatch->refereeAssignments()->delete();
            foreach ($data['referees'] as $role => $refereeId) {
                if ($refereeId) $lockedMatch->refereeAssignments()->create(['referee_id' => $refereeId, 'role' => $role, 'assigned_by' => $actor->id]);
            }
            $new = $this->snapshot($lockedMatch->fresh()->load('refereeAssignments'));
            $lockedMatch->scheduleChanges()->create([
                'type' => $old['scheduled_at'] ? 'rescheduled' : 'scheduled',
                'old_values' => $old,
                'new_values' => $new,
                'reason' => $data['reason'],
                'performed_by' => $actor->id,
                'occurred_at' => now(),
            ]);
            return $lockedMatch->fresh();
        });
    }

    private function conflicts(GameMatch $match, Carbon $startsAt, Carbon $reservedUntil, int $buffer, callable $scope): bool
    {
        return $scope(GameMatch::query()->whereKeyNot($match->id)
            ->whereIn('status', ['draft', 'scheduled', 'in_progress', 'suspended'])
            ->whereNotNull('scheduled_at')->whereNotNull('scheduled_end_at'))
            ->get(['id', 'scheduled_at', 'scheduled_end_at'])
            ->contains(fn (GameMatch $existing) => $existing->scheduled_at->lt($reservedUntil)
                && $existing->scheduled_end_at->copy()->addMinutes($buffer)->gt($startsAt));
    }

    private function assertNoFieldConflict(GameMatch $match, int $fieldId, Carbon $startsAt, Carbon $reservedUntil, int $buffer): void
    {
        if ($this->conflicts($match, $startsAt, $reservedUntil, $buffer, fn ($query) => $query->where('playing_field_id', $fieldId))) {
            throw ValidationException::withMessages(['playing_field_id' => 'La cancha ya tiene otro partido en un horario incompatible.']);
        }
    }

    private function assertNoTeamConflict(GameMatch $match, Carbon $startsAt, Carbon $reservedUntil, int $buffer): void
    {
        $teams = [$match->home_team_participation_id, $match->away_team_participation_id];
        if ($this->conflicts($match, $startsAt, $reservedUntil, $buffer, fn ($query) => $query->where(fn ($teamsQuery) => $teamsQuery
            ->whereIn('home_team_participation_id', $teams)->orWhereIn('away_team_participation_id', $teams)))) {
            throw ValidationException::withMessages(['scheduled_at' => 'Uno de los equipos ya tiene otro partido en un horario incompatible.']);
        }
    }

    private function assertNoRefereeConflict(GameMatch $match, array $refereeIds, Carbon $startsAt, Carbon $reservedUntil, int $buffer): void
    {
        if ($this->conflicts($match, $startsAt, $reservedUntil, $buffer, fn ($query) => $query->whereHas('refereeAssignments', fn ($assignments) => $assignments->whereIn('referee_id', $refereeIds)))) {
            throw ValidationException::withMessages(['central_referee_id' => 'Uno de los árbitros ya está asignado a otro partido en un horario incompatible.']);
        }
    }

    private function snapshot(GameMatch $match): array
    {
        return [
            'playing_field_id' => $match->playing_field_id,
            'scheduled_at' => $match->scheduled_at?->toIso8601String(),
            'scheduled_end_at' => $match->scheduled_end_at?->toIso8601String(),
            'status' => $match->status->value,
            'referees' => $match->refereeAssignments->pluck('referee_id', 'role')->all(),
        ];
    }
}
