<?php

namespace App\Domain\Scheduling\Services;

use App\Domain\Competition\Enums\CompetitionFormat;
use App\Domain\Competition\Models\Competition;
use App\Domain\Scheduling\Models\GameMatch;
use App\Domain\Scheduling\Models\Matchday;
use App\Domain\Team\Models\TeamParticipation;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class RoundRobinScheduleGenerator
{
    public function generate(Competition $competition, CarbonInterface $firstDate, int $daysBetween, int $userId): array
    {
        if (! in_array($competition->format, [CompetitionFormat::RoundRobin, CompetitionFormat::DoubleRoundRobin], true)) {
            throw ValidationException::withMessages(['competition_id' => 'La generación automática está disponible para todos contra todos. Usa captura manual para este formato.']);
        }
        if ($competition->matchdays()->exists()) {
            throw ValidationException::withMessages(['competition_id' => 'La competencia ya tiene jornadas. Elimínalas o utiliza captura manual.']);
        }

        $participations = $competition->teamParticipations()->where('status', 'active')->orderBy('id')->get();
        if ($participations->count() < 2) {
            throw ValidationException::withMessages(['competition_id' => 'Se necesitan al menos dos equipos activos para generar el calendario.']);
        }

        $rounds = $this->pairings($participations->all());
        if ($competition->format === CompetitionFormat::DoubleRoundRobin) {
            $returnRounds = array_map(fn (array $round) => array_map(fn (array $pair) => [$pair[1], $pair[0]], $round), $rounds);
            $rounds = [...$rounds, ...$returnRounds];
        }
        $duration = $this->durationMinutes($competition);

        return DB::transaction(function () use ($competition, $rounds, $firstDate, $daysBetween, $userId, $duration): array {
            Competition::whereKey($competition->id)->lockForUpdate()->firstOrFail();
            if ($competition->matchdays()->exists()) {
                throw ValidationException::withMessages(['competition_id' => 'La competencia ya tiene jornadas. Utiliza captura manual para agregar jornadas adicionales.']);
            }
            $created = [];
            foreach ($rounds as $index => $pairs) {
                $date = $firstDate->copy()->addDays($index * $daysBetween);
                $matchday = Matchday::create([
                    'competition_id' => $competition->id,
                    'number' => $index + 1,
                    'name' => 'Jornada '.($index + 1),
                    'phase' => 'regular',
                    'starts_on' => $date->toDateString(),
                    'ends_on' => $date->toDateString(),
                    'status' => 'draft',
                    'created_by' => $userId,
                ]);
                foreach ($pairs as [$home, $away]) {
                    GameMatch::create([
                        'competition_id' => $competition->id,
                        'matchday_id' => $matchday->id,
                        'home_team_participation_id' => $home->id,
                        'away_team_participation_id' => $away->id,
                        'duration_minutes' => $duration,
                        'status' => 'draft',
                        'created_by' => $userId,
                    ]);
                }
                $created[] = $matchday;
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
