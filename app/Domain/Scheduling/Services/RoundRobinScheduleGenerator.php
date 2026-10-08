<?php

namespace App\Domain\Scheduling\Services;

use App\Domain\Competition\Enums\CompetitionFormat;
use App\Domain\Competition\Models\Competition;
use App\Domain\Identity\Models\User;
use App\Domain\Scheduling\Models\GameMatch;
use App\Domain\Scheduling\Models\Matchday;
use Carbon\Carbon;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class RoundRobinScheduleGenerator
{
    public function __construct(private readonly AutomaticScheduleAllocator $allocator) {}

    public function generate(
        Competition $competition,
        CarbonInterface $firstDate,
        int $daysBetween,
        int $userId,
        string $scope = 'all',
        bool $autoSchedule = false,
        string $reason = 'Generación automática del calendario',
    ): array {
        if (! in_array($competition->format, [CompetitionFormat::RoundRobin, CompetitionFormat::DoubleRoundRobin], true)) {
            throw ValidationException::withMessages(['competition_id' => 'La generación automática está disponible para todos contra todos. Usa captura manual para este formato.']);
        }
        $participations = $competition->teamParticipations()->where('status', 'active')->orderBy('id')->get();
        if ($participations->count() < 2) {
            throw ValidationException::withMessages(['competition_id' => 'Se necesitan al menos dos equipos activos para generar el calendario.']);
        }

        $firstLeg = $this->pairings($participations->all());
        $roundsPerLeg = count($firstLeg);
        $rounds = array_map(fn (array $pairs, int $index) => ['pairs' => $pairs, 'leg' => 1, 'round' => $index + 1], $firstLeg, array_keys($firstLeg));
        if ($competition->format === CompetitionFormat::DoubleRoundRobin) {
            foreach ($firstLeg as $index => $pairs) {
                $rounds[] = [
                    'pairs' => array_map(fn (array $pair) => [$pair[1], $pair[0]], $pairs),
                    'leg' => 2,
                    'round' => $roundsPerLeg + $index + 1,
                ];
            }
        }

        $existing = $competition->matches()->get(['home_team_participation_id', 'away_team_participation_id', 'leg_number'])
            ->groupBy(fn (GameMatch $match) => $this->pairingKey($match->home_team_participation_id, $match->away_team_participation_id));
        $pending = collect($rounds)->map(function (array $round) use ($existing): array {
            $round['pairs'] = array_values(array_filter($round['pairs'], function (array $pair) use ($existing, $round): bool {
                $matches = $existing->get($this->pairingKey($pair[0]->id, $pair[1]->id), collect());
                return $matches->count() < $round['leg']
                    && ! $matches->contains(fn (GameMatch $match) => (int) $match->leg_number === $round['leg']);
            }));
            return $round;
        })->filter(fn (array $round) => count($round['pairs']) > 0)->values();

        if ($pending->isEmpty()) {
            throw ValidationException::withMessages(['competition_id' => 'El calendario ya contiene todos los enfrentamientos previstos.']);
        }
        if ($scope === 'next') $pending = $pending->take(1);
        $duration = $this->durationMinutes($competition);
        $actor = User::findOrFail($userId);

        return DB::transaction(function () use ($competition, $pending, $firstDate, $daysBetween, $actor, $duration, $autoSchedule, $reason): array {
            Competition::whereKey($competition->id)->lockForUpdate()->firstOrFail();
            $created = [];
            $nextNumber = ((int) $competition->matchdays()->max('number')) + 1;

            foreach ($pending as $offset => $round) {
                if ($competition->matchdays()->where('generation_round', $round['round'])->exists()) continue;
                $date = Carbon::parse($firstDate)->addDays($offset * $daysBetween);
                $matchday = Matchday::create([
                    'competition_id' => $competition->id,
                    'number' => $nextNumber++,
                    'name' => 'Jornada '.($nextNumber - 1),
                    'phase' => 'regular', 'starts_on' => $date->toDateString(), 'ends_on' => $date->toDateString(),
                    'status' => 'draft', 'leg_number' => $round['leg'], 'generation_round' => $round['round'],
                    'generated_automatically' => true, 'created_by' => $actor->id,
                ]);
                $matches = collect();
                foreach ($round['pairs'] as [$home, $away]) {
                    $key = $this->pairingKey($home->id, $away->id);
                    $alreadyExists = GameMatch::where('competition_id', $competition->id)->where('leg_number', $round['leg'])
                        ->where(fn ($query) => $query
                            ->where(fn ($teams) => $teams->where('home_team_participation_id', $home->id)->where('away_team_participation_id', $away->id))
                            ->orWhere(fn ($teams) => $teams->where('home_team_participation_id', $away->id)->where('away_team_participation_id', $home->id)))
                        ->exists();
                    if ($alreadyExists) continue;
                    $matches->push(GameMatch::create([
                        'competition_id' => $competition->id, 'matchday_id' => $matchday->id,
                        'home_team_participation_id' => $home->id, 'away_team_participation_id' => $away->id,
                        'duration_minutes' => $duration, 'status' => 'draft', 'leg_number' => $round['leg'],
                        'pairing_key' => $key, 'created_by' => $actor->id,
                    ])->load('homeParticipation', 'awayParticipation'));
                }
                if ($matches->isEmpty()) {
                    $matchday->delete();
                    continue;
                }
                if ($autoSchedule) $this->allocator->allocate($competition, $matches, $date, $actor, $reason);
                $created[] = $matchday;
            }

            if (! count($created)) {
                throw ValidationException::withMessages(['competition_id' => 'No se encontraron nuevas jornadas que puedan generarse.']);
            }
            return $created;
        });
    }

    public function durationMinutes(Competition $competition): int
    {
        $settings = $competition->regulation->league_settings_snapshot;
        $periods = max(1, (int) ($settings['match_periods'] ?? 2));
        $periodMinutes = max(1, (int) ($settings['period_duration_minutes'] ?? 45));
        $halftime = max(0, (int) ($settings['halftime_minutes'] ?? 0));
        return ($periods * $periodMinutes) + (max(0, $periods - 1) * $halftime);
    }

    public function pairingKey(int $first, int $second): string
    {
        return min($first, $second).':'.max($first, $second);
    }

    private function pairings(array $participations): array
    {
        $teams = $participations;
        if (count($teams) % 2 !== 0) $teams[] = null;
        $count = count($teams);
        $rounds = [];
        for ($round = 0; $round < $count - 1; $round++) {
            $pairs = [];
            for ($index = 0; $index < $count / 2; $index++) {
                $first = $teams[$index];
                $second = $teams[$count - 1 - $index];
                if (! $first || ! $second) continue;
                $pairs[] = ($round + $index) % 2 === 0 ? [$first, $second] : [$second, $first];
            }
            $rounds[] = $pairs;
            $fixed = array_shift($teams);
            $last = array_pop($teams);
            array_unshift($teams, $last);
            array_unshift($teams, $fixed);
        }
        return $rounds;
    }
}
