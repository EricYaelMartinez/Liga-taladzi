<?php

namespace App\Domain\Scheduling\Services;

use App\Domain\Scheduling\Enums\MatchStatus;
use App\Domain\Scheduling\Enums\MatchdayStatus;
use App\Domain\Scheduling\Models\GameMatch;
use App\Domain\Scheduling\Models\Matchday;
use Illuminate\Support\Facades\DB;

class MatchLifecycleService
{
    public function syncAll(): array
    {
        $started = 0;
        GameMatch::query()
            ->where('status', MatchStatus::Scheduled->value)
            ->whereNotNull('scheduled_at')
            ->where('scheduled_at', '<=', now())
            ->orderBy('id')
            ->chunkById(100, function ($matches) use (&$started): void {
                foreach ($matches as $match) {
                    if ($this->syncMatch($match)->status === MatchStatus::InProgress) $started++;
                }
            });

        return ['started' => $started, 'closed_matchdays' => $this->syncMatchdays()];
    }

    public function syncForLeague(int $leagueId): array
    {
        $started = 0;
        GameMatch::query()
            ->whereHas('competition.tournament.season', fn ($query) => $query->where('league_id', $leagueId))
            ->where('status', MatchStatus::Scheduled->value)
            ->whereNotNull('scheduled_at')
            ->where('scheduled_at', '<=', now())
            ->orderBy('id')
            ->chunkById(100, function ($matches) use (&$started): void {
                foreach ($matches as $match) {
                    if ($this->syncMatch($match)->status === MatchStatus::InProgress) $started++;
                }
            });

        return ['started' => $started, 'closed_matchdays' => $this->syncMatchdays($leagueId)];
    }

    public function syncMatch(GameMatch $match): GameMatch
    {
        if ($match->status !== MatchStatus::Scheduled || ! $match->scheduled_at || $match->scheduled_at->isFuture()) {
            return $match;
        }

        return DB::transaction(function () use ($match): GameMatch {
            $locked = GameMatch::whereKey($match->id)->lockForUpdate()->firstOrFail();
            if ($locked->status === MatchStatus::Scheduled && $locked->scheduled_at && ! $locked->scheduled_at->isFuture()) {
                $locked->update(['status' => MatchStatus::InProgress]);
                $locked->scheduleChanges()->create([
                    'type' => 'status_changed',
                    'old_values' => ['status' => MatchStatus::Scheduled->value],
                    'new_values' => ['status' => MatchStatus::InProgress->value],
                    'reason' => 'Inicio automático al alcanzar la fecha y hora programada.',
                    'performed_by' => null,
                    'occurred_at' => now(),
                ]);
            }

            return $locked->fresh();
        });
    }

    public function syncMatchdays(?int $leagueId = null): int
    {
        $closed = 0;
        Matchday::query()
            ->where('status', MatchdayStatus::Published->value)
            ->when($leagueId, fn ($query) => $query->whereHas('competition.tournament.season', fn ($season) => $season->where('league_id', $leagueId)))
            ->whereHas('matches')
            ->with('matches:id,matchday_id,status')
            ->orderBy('id')
            ->chunkById(100, function ($matchdays) use (&$closed): void {
                foreach ($matchdays as $matchday) {
                    $statuses = $matchday->matches->pluck('status')->map(fn ($status) => $status->value);
                    if ($statuses->every(fn (string $status) => $status === MatchStatus::Cancelled->value)) {
                        $matchday->update(['status' => MatchdayStatus::Cancelled]);
                        $closed++;
                    } elseif ($statuses->every(fn (string $status) => in_array($status, [MatchStatus::Finished->value, MatchStatus::Cancelled->value], true))) {
                        $matchday->update(['status' => MatchdayStatus::Completed]);
                        $closed++;
                    }
                }
            });

        return $closed;
    }
}
