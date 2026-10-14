<?php

namespace App\Domain\Discipline\Services;

use App\Domain\Competition\Models\Competition;
use App\Domain\Discipline\Models\DisciplinePolicy;
use App\Domain\Discipline\Models\Sanction;
use App\Domain\Player\Enums\PlayerStatus;
use App\Domain\Player\Models\Player;
use App\Domain\Player\Models\PlayerRegistration;
use App\Domain\Scheduling\Enums\MatchReportStatus;
use App\Domain\Scheduling\Models\MatchReport;
use App\Domain\Scheduling\Models\MatchReportEvent;
use App\Domain\Team\Models\Team;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class DisciplineService
{
    public function syncLeague(int $leagueId): Collection
    {
        $created = collect();
        MatchReport::query()
            ->where('status', MatchReportStatus::Approved->value)
            ->whereHas('match.competition.tournament.season', fn ($query) => $query->where('league_id', $leagueId))
            ->orderBy('approved_at')->orderBy('id')->get()
            ->each(fn (MatchReport $report) => $created->push(...$this->syncReport($report)));
        return $created;
    }

    public function syncReport(MatchReport $report): Collection
    {
        if ($report->status !== MatchReportStatus::Approved) return collect();
        $report->loadMissing('match.competition.tournament.season', 'match.homeParticipation', 'match.awayParticipation', 'events.playerRegistration.player');
        $match = $report->match;
        $season = $match->competition->tournament->season;
        $policy = DisciplinePolicy::firstOrCreate(
            ['league_id' => $season->league_id],
            [
                'automatic_sanctions_enabled' => true,
                'yellow_card_threshold' => 5,
                'yellow_suspension_matches' => 1,
                'second_yellow_suspension_matches' => 1,
                'red_card_suspension_matches' => 1,
            ],
        );

        return DB::transaction(function () use ($report, $match, $season, $policy): Collection {
            $this->fulfillSuspensions($report);
            if (! $policy->automatic_sanctions_enabled) return collect();

            $created = collect();
            $eventsByPlayer = $report->events->filter(fn ($event) => $event->playerRegistration?->player)->groupBy(fn ($event) => $event->playerRegistration->player_id);
            foreach ($eventsByPlayer as $playerId => $events) {
                $player = $events->first()->playerRegistration->player;
                $hasRed = $events->contains('type', 'red_card');
                if ($hasRed) {
                    foreach ($events->where('type', 'red_card') as $event) {
                        $sanction = $this->automaticSuspension($report, $event, $player, $policy->red_card_suspension_matches, 'Expulsión directa registrada en cédula aprobada.');
                        if ($sanction->wasRecentlyCreated) $created->push($sanction);
                    }
                } else {
                    foreach ($events->where('type', 'second_yellow') as $event) {
                        $sanction = $this->automaticSuspension($report, $event, $player, $policy->second_yellow_suspension_matches, 'Expulsión por segunda amonestación registrada en cédula aprobada.');
                        if ($sanction->wasRecentlyCreated) $created->push($sanction);
                    }
                }

                if ($events->contains('type', 'yellow_card')) {
                    $yellowCount = MatchReportEvent::query()->where('type', 'yellow_card')
                        ->whereHas('playerRegistration', fn ($query) => $query->where('player_id', $playerId))
                        ->whereHas('report', fn ($query) => $query->where('status', MatchReportStatus::Approved->value)
                            ->where('approved_at', '<=', $report->approved_at)
                            ->whereHas('match.competition.tournament', fn ($tournament) => $tournament->where('season_id', $season->id)))
                        ->count();
                    $buckets = intdiv($yellowCount, $policy->yellow_card_threshold);
                    for ($bucket = 1; $bucket <= $buckets; $bucket++) {
                        $sanction = $this->automaticSuspension(
                            $report,
                            null,
                            $player,
                            $policy->yellow_suspension_matches,
                            "Acumulación de {$policy->yellow_card_threshold} amonestaciones en la temporada.",
                            "yellow:{$season->id}:{$player->id}:{$bucket}",
                        );
                        if ($sanction->wasRecentlyCreated) $created->push($sanction);
                    }
                }
            }
            return $created;
        });
    }

    public function createManual(array $data, int $leagueId, int $actorId): Sanction
    {
        return DB::transaction(function () use ($data, $leagueId, $actorId): Sanction {
            $player = $data['subject_type'] === 'player' ? Player::where('league_id', $leagueId)->findOrFail($data['player_id']) : null;
            $team = in_array($data['subject_type'], ['team', 'staff'], true) ? Team::where('league_id', $leagueId)->findOrFail($data['team_id']) : null;
            $competition = ! empty($data['competition_id']) ? Competition::with('tournament:id,season_id')->findOrFail($data['competition_id']) : null;
            $registration = $player?->registrations()->whereIn('status', ['active', 'suspended'])->latest('id')->first();
            $teamId = $team?->id ?? $registration?->team_id;
            $issuedAt = now();
            $deadlineHours = DB::table('league_settings')->where('league_id', $leagueId)->value('appeal_deadline_hours') ?? 2;
            $sanction = Sanction::create([
                'league_id' => $leagueId,
                'season_id' => $data['season_id'] ?? $competition?->tournament?->season_id ?? $registration?->season_id,
                'competition_id' => $data['competition_id'] ?? $registration?->competition_id,
                'team_id' => $teamId,
                'player_id' => $player?->id,
                'subject_type' => $data['subject_type'],
                'subject_name' => $player?->full_name ?? ($data['subject_type'] === 'staff' ? $data['subject_name'] : $team?->name),
                'kind' => $data['kind'],
                'source' => 'manual',
                'match_id' => $data['match_id'] ?? null,
                'reason' => $data['reason'],
                'legal_basis' => $data['legal_basis'] ?? null,
                'matches_total' => $data['kind'] === 'suspension' ? $data['matches_total'] : 0,
                'points_deducted' => $data['kind'] === 'points_deduction' ? $data['points_deducted'] : 0,
                'fine_amount' => $data['kind'] === 'fine' ? $data['fine_amount'] : null,
                'status' => 'active',
                'issued_at' => $issuedAt,
                'appeal_deadline_at' => $issuedAt->copy()->addHours($deadlineHours),
                'created_by' => $actorId,
            ]);
            if ($player && $registration && $data['kind'] === 'suspension') $player->update(['status' => PlayerStatus::Suspended]);
            return $sanction;
        });
    }

    public function resolveAppeal(Sanction $sanction, string $action, array $data, int $actorId): void
    {
        DB::transaction(function () use ($sanction, $action, $data, $actorId): void {
            $appeal = $sanction->appeal()->lockForUpdate()->firstOrFail();
            if ($appeal->status !== 'submitted') throw ValidationException::withMessages(['appeal' => 'La apelación ya fue resuelta.']);
            if ($action === 'reduce') {
                if ($sanction->kind === 'suspension') {
                    $newTotal = (int) ($data['matches_total'] ?? 0);
                    if ($newTotal < $sanction->matches_served || $newTotal >= $sanction->matches_total) throw ValidationException::withMessages(['matches_total' => 'La nueva duración debe ser menor que la original y no menor que los partidos ya cumplidos.']);
                    $sanction->matches_total = $newTotal;
                } elseif ($sanction->kind === 'points_deduction') {
                    $newPoints = (int) ($data['points_deducted'] ?? 0);
                    if ($newPoints >= $sanction->points_deducted) throw ValidationException::withMessages(['points_deducted' => 'La nueva deducción debe ser menor que la original.']);
                    $sanction->points_deducted = $newPoints;
                } elseif ($sanction->kind === 'fine') {
                    $newAmount = (float) ($data['fine_amount'] ?? 0);
                    if ($newAmount <= 0 || $newAmount >= (float) $sanction->fine_amount) throw ValidationException::withMessages(['fine_amount' => 'El nuevo importe debe ser mayor que cero y menor que el original.']);
                    $sanction->fine_amount = $newAmount;
                } else {
                    throw ValidationException::withMessages(['action' => 'Este tipo de sanción solo puede confirmarse o revocarse.']);
                }
                $sanction->status = $sanction->kind === 'suspension' && $sanction->matches_served >= $sanction->matches_total ? 'completed' : 'active';
                $sanction->completed_at = $sanction->status === 'completed' ? now() : null;
            } elseif ($action === 'revoke') {
                $sanction->status = 'revoked';
                $sanction->revoked_at = now();
            }
            $sanction->resolved_by = $actorId;
            $sanction->resolution_notes = $data['resolution'];
            $sanction->save();
            $appeal->update(['status' => match ($action) { 'uphold' => 'upheld', 'reduce' => 'reduced', default => 'revoked' }, 'reviewed_by' => $actorId, 'reviewed_at' => now(), 'resolution' => $data['resolution']]);
            if ($sanction->player_id) $this->refreshPlayerStatus($sanction->player);
        });
    }

    private function automaticSuspension(MatchReport $report, $event, Player $player, int $matches, string $reason, ?string $sourceKey = null): Sanction
    {
        $match = $report->match;
        $season = $match->competition->tournament->season;
        $registration = $event?->playerRegistration ?? $player->registrations()->where('season_id', $season->id)->latest('id')->first();
        $issuedAt = $report->approved_at ?? now();
        $deadlineHours = DB::table('league_settings')->where('league_id', $season->league_id)->value('appeal_deadline_hours') ?? 2;
        $sanction = Sanction::firstOrCreate(
            ['league_id' => $season->league_id, 'source_key' => $sourceKey ?? "event:{$event->id}"],
            [
                'season_id' => $season->id, 'competition_id' => $match->competition_id,
                'team_id' => $registration?->team_id, 'player_id' => $player->id,
                'subject_type' => 'player', 'subject_name' => $player->full_name,
                'kind' => 'suspension', 'source' => 'automatic',
                'match_report_id' => $report->id, 'match_id' => $match->id, 'source_event_id' => $event?->id,
                'reason' => $reason, 'matches_total' => $matches, 'status' => 'active',
                'issued_at' => $issuedAt, 'appeal_deadline_at' => $issuedAt->copy()->addHours($deadlineHours),
                'created_by' => $report->reviewed_by,
            ],
        );
        if ($sanction->wasRecentlyCreated) $player->update(['status' => PlayerStatus::Suspended]);
        return $sanction;
    }

    private function fulfillSuspensions(MatchReport $report): void
    {
        $match = $report->match;
        $teamParticipationIds = [$match->home_team_participation_id, $match->away_team_participation_id];
        $teamIds = [$match->homeParticipation->team_id, $match->awayParticipation->team_id];
        $playerIds = PlayerRegistration::whereIn('team_participation_id', $teamParticipationIds)->whereIn('status', ['active', 'suspended'])->pluck('player_id');
        Sanction::query()->where('league_id', $match->competition->tournament->season->league_id)
            ->where('kind', 'suspension')->where('status', 'active')
            ->where(function ($query) use ($playerIds, $teamIds): void {
                $query->whereIn('player_id', $playerIds)->orWhere(function ($teams) use ($teamIds): void {
                    $teams->whereIn('subject_type', ['team', 'staff'])->whereIn('team_id', $teamIds);
                });
            })
            ->where(fn ($query) => $query->whereNull('match_id')->orWhere('match_id', '!=', $match->id))
            ->lockForUpdate()->get()->each(function (Sanction $sanction) use ($match, $report): void {
                $sanction->servedMatches()->firstOrCreate(['match_id' => $match->id], ['served_at' => $report->approved_at ?? now()]);
                $sanction->matches_served = $sanction->servedMatches()->count();
                if ($sanction->matches_served >= $sanction->matches_total) {
                    $sanction->status = 'completed';
                    $sanction->completed_at = $report->approved_at ?? now();
                }
                $sanction->save();
                if ($sanction->status === 'completed' && $sanction->player) $this->refreshPlayerStatus($sanction->player);
            });
    }

    private function refreshPlayerStatus(Player $player): void
    {
        $hasSuspension = Sanction::where('player_id', $player->id)->where('kind', 'suspension')->where('status', 'active')->exists();
        if (! $hasSuspension && $player->status === PlayerStatus::Suspended) {
            $hasCurrentRegistration = $player->registrations()->whereIn('status', ['active', 'suspended'])->exists();
            $player->update(['status' => $hasCurrentRegistration ? PlayerStatus::Active : PlayerStatus::Inactive]);
        }
    }
}
