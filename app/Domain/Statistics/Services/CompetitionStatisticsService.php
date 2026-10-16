<?php

namespace App\Domain\Statistics\Services;

use App\Domain\Competition\Models\Competition;
use App\Domain\Competition\Models\Regulation;
use App\Domain\Discipline\Models\Sanction;
use App\Domain\Scheduling\Enums\MatchReportStatus;
use App\Domain\Scheduling\Models\MatchReport;
use Illuminate\Support\Collection;

class CompetitionStatisticsService
{
    public function calculate(Competition $competition, bool $publishedOnly = false): array
    {
        $competition->loadMissing('regulation.tiebreakers', 'teamParticipations.team');
        $regulation = $competition->regulation;
        $reports = MatchReport::query()
            ->where('status', MatchReportStatus::Approved->value)
            ->whereHas('match', fn ($query) => $query->where('competition_id', $competition->id))
            ->when($publishedOnly, fn ($query) => $query->whereHas(
                'match.matchday',
                fn ($matchday) => $matchday->whereIn('status', ['published', 'completed']),
            ))
            ->with([
                'match.matchday:id,name,number',
                'match.homeParticipation.team:id,name,short_name',
                'match.awayParticipation.team:id,name,short_name',
                'events.playerRegistration.player:id,full_name',
                'events.teamParticipation.team:id,name,short_name',
            ])->get();

        $standings = $competition->teamParticipations->mapWithKeys(fn ($participation) => [
            $participation->id => $this->emptyStanding($participation->id, $participation->registered_name, $participation->team?->short_name),
        ])->all();
        $directMatches = [];
        $results = [];
        $scorers = [];
        $discipline = [];

        foreach ($reports as $report) {
            $match = $report->match;
            $homeId = $match->home_team_participation_id;
            $awayId = $match->away_team_participation_id;
            $standings[$homeId] ??= $this->emptyStanding($homeId, $match->homeParticipation->registered_name, $match->homeParticipation->team?->short_name);
            $standings[$awayId] ??= $this->emptyStanding($awayId, $match->awayParticipation->registered_name, $match->awayParticipation->team?->short_name);

            $this->applyResult($standings[$homeId], $standings[$awayId], $report->home_score, $report->away_score, $regulation);
            $directMatches[] = ['home_id' => $homeId, 'away_id' => $awayId, 'home_score' => $report->home_score, 'away_score' => $report->away_score];

            foreach ($report->events as $event) {
                $teamId = $event->team_participation_id;
                if ($teamId && isset($standings[$teamId])) {
                    $standings[$teamId]['fair_play_points'] += $this->fairPlayValue($event->type, $regulation);
                }
                if ($event->type === 'goal' && $event->player_registration_id && $event->playerRegistration?->player) {
                    $key = $event->player_registration_id;
                    $scorers[$key] ??= ['registration_id' => $key, 'player' => $event->playerRegistration->player->full_name, 'team' => $event->teamParticipation?->registered_name ?? 'Sin equipo', 'goals' => 0];
                    $scorers[$key]['goals']++;
                }
                if (in_array($event->type, ['yellow_card', 'second_yellow', 'red_card'], true) && $event->player_registration_id && $event->playerRegistration?->player) {
                    $key = $event->player_registration_id;
                    $discipline[$key] ??= ['registration_id' => $key, 'player' => $event->playerRegistration->player->full_name, 'team' => $event->teamParticipation?->registered_name ?? 'Sin equipo', 'yellow_cards' => 0, 'second_yellows' => 0, 'red_cards' => 0, 'fair_play_points' => 0];
                    if ($event->type === 'yellow_card') $discipline[$key]['yellow_cards']++;
                    if ($event->type === 'second_yellow') $discipline[$key]['second_yellows']++;
                    if ($event->type === 'red_card') $discipline[$key]['red_cards']++;
                    $discipline[$key]['fair_play_points'] += $this->fairPlayValue($event->type, $regulation);
                }
            }

            $results[] = [
                'match_id' => $match->id,
                'matchday' => $match->matchday?->name,
                'scheduled_at' => $match->scheduled_at?->toIso8601String(),
                'home_team' => $match->homeParticipation->registered_name,
                'away_team' => $match->awayParticipation->registered_name,
                'home_score' => $report->home_score,
                'away_score' => $report->away_score,
                'home_no_show' => $report->home_no_show,
                'away_no_show' => $report->away_no_show,
                'folio' => $report->folio,
                'approved_at' => $report->approved_at?->toIso8601String(),
            ];
        }

        $deductions = Sanction::query()->where('competition_id', $competition->id)->where('kind', 'points_deduction')->whereIn('status', ['active', 'completed'])->selectRaw('team_id, SUM(points_deducted) AS total')->groupBy('team_id')->pluck('total', 'team_id');
        foreach ($competition->teamParticipations as $participation) {
            if (! isset($standings[$participation->id])) continue;
            $deducted = (int) ($deductions[$participation->team_id] ?? 0);
            $standings[$participation->id]['points_deducted'] = $deducted;
            $standings[$participation->id]['points'] -= $deducted;
        }
        $orderedStandings = $this->sortStandings(array_values($standings), $regulation->tiebreakers->pluck('criterion')->all(), $directMatches, $regulation);
        foreach ($orderedStandings as $index => &$row) $row['position'] = $index + 1;

        usort($scorers, fn (array $a, array $b) => [$b['goals'], $a['player']] <=> [$a['goals'], $b['player']]);
        usort($discipline, fn (array $a, array $b) => [$b['red_cards'], $b['second_yellows'], $b['yellow_cards'], $a['player']] <=> [$a['red_cards'], $a['second_yellows'], $a['yellow_cards'], $b['player']]);
        usort($results, fn (array $a, array $b) => strcmp($b['scheduled_at'] ?? '', $a['scheduled_at'] ?? ''));

        $totalGoals = collect($results)->sum(fn (array $result) => $result['home_score'] + $result['away_score']);

        return [
            'standings' => $orderedStandings,
            'results' => $results,
            'scorers' => array_values($scorers),
            'discipline' => array_values($discipline),
            'summary' => [
                'official_matches' => count($results),
                'total_goals' => $totalGoals,
                'average_goals' => count($results) ? round($totalGoals / count($results), 2) : 0,
                'registered_teams' => count($standings),
            ],
            'tiebreakers' => $regulation->tiebreakers->map(fn ($item) => ['criterion' => $item->criterion, 'priority' => $item->priority])->values(),
        ];
    }

    private function emptyStanding(int $id, string $name, ?string $shortName): array
    {
        return ['team_participation_id' => $id, 'team' => $name, 'short_name' => $shortName ?: $name, 'played' => 0, 'won' => 0, 'drawn' => 0, 'lost' => 0, 'goals_for' => 0, 'goals_against' => 0, 'goal_difference' => 0, 'points' => 0, 'points_deducted' => 0, 'fair_play_points' => 0];
    }

    private function applyResult(array &$home, array &$away, int $homeScore, int $awayScore, Regulation $regulation): void
    {
        $home['played']++; $away['played']++;
        $home['goals_for'] += $homeScore; $home['goals_against'] += $awayScore;
        $away['goals_for'] += $awayScore; $away['goals_against'] += $homeScore;
        $home['goal_difference'] = $home['goals_for'] - $home['goals_against'];
        $away['goal_difference'] = $away['goals_for'] - $away['goals_against'];
        if ($homeScore > $awayScore) {
            $home['won']++; $away['lost']++;
            $home['points'] += $regulation->points_win; $away['points'] += $regulation->points_loss;
        } elseif ($homeScore < $awayScore) {
            $away['won']++; $home['lost']++;
            $away['points'] += $regulation->points_win; $home['points'] += $regulation->points_loss;
        } else {
            $home['drawn']++; $away['drawn']++;
            $home['points'] += $regulation->points_draw; $away['points'] += $regulation->points_draw;
        }
    }

    private function fairPlayValue(string $type, Regulation $regulation): int
    {
        return match ($type) {
            'yellow_card' => $regulation->fair_play_yellow_points,
            'second_yellow' => $regulation->fair_play_second_yellow_points,
            'red_card' => $regulation->fair_play_red_points,
            default => 0,
        };
    }

    private function sortStandings(array $rows, array $criteria, array $matches, Regulation $regulation): array
    {
        $groups = [$rows];
        foreach ($criteria as $criterion) {
            $next = [];
            foreach ($groups as $group) {
                if (count($group) < 2) { $next[] = $group; continue; }
                $values = $this->criterionValues($criterion, $group, $matches, $regulation);
                usort($group, function (array $a, array $b) use ($values, $criterion): int {
                    $left = $values[$a['team_participation_id']];
                    $right = $values[$b['team_participation_id']];
                    return $criterion === 'fair_play' ? $this->compareValues($left, $right) : $this->compareValues($right, $left);
                });
                $bucket = [];
                $last = null;
                foreach ($group as $row) {
                    $key = json_encode($values[$row['team_participation_id']]);
                    if ($last !== null && $key !== $last) { $next[] = $bucket; $bucket = []; }
                    $bucket[] = $row; $last = $key;
                }
                if ($bucket) $next[] = $bucket;
            }
            $groups = $next;
        }
        return collect($groups)->flatMap(function (array $group): array {
            usort($group, fn (array $a, array $b) => strcasecmp($a['team'], $b['team']) ?: $a['team_participation_id'] <=> $b['team_participation_id']);
            return $group;
        })->values()->all();
    }

    private function criterionValues(string $criterion, array $group, array $matches, Regulation $regulation): array
    {
        if ($criterion !== 'head_to_head') {
            return collect($group)->mapWithKeys(fn (array $row) => [$row['team_participation_id'] => match ($criterion) {
                'points' => $row['points'],
                'goal_difference' => $row['goal_difference'],
                'goals_for' => $row['goals_for'],
                'fair_play' => $row['fair_play_points'],
                default => 0,
            }])->all();
        }

        $ids = collect($group)->pluck('team_participation_id')->all();
        $mini = collect($ids)->mapWithKeys(fn (int $id) => [$id => ['points' => 0, 'goal_difference' => 0, 'goals_for' => 0]])->all();
        foreach ($matches as $match) {
            if (! in_array($match['home_id'], $ids, true) || ! in_array($match['away_id'], $ids, true)) continue;
            $home = &$mini[$match['home_id']]; $away = &$mini[$match['away_id']];
            $home['goals_for'] += $match['home_score']; $away['goals_for'] += $match['away_score'];
            $home['goal_difference'] += $match['home_score'] - $match['away_score'];
            $away['goal_difference'] += $match['away_score'] - $match['home_score'];
            if ($match['home_score'] > $match['away_score']) { $home['points'] += $regulation->points_win; $away['points'] += $regulation->points_loss; }
            elseif ($match['home_score'] < $match['away_score']) { $away['points'] += $regulation->points_win; $home['points'] += $regulation->points_loss; }
            else { $home['points'] += $regulation->points_draw; $away['points'] += $regulation->points_draw; }
            unset($home, $away);
        }
        return collect($mini)->map(fn (array $value) => [$value['points'], $value['goal_difference'], $value['goals_for']])->all();
    }

    private function compareValues(int|array $left, int|array $right): int
    {
        if (is_int($left)) return $left <=> $right;
        foreach ($left as $index => $value) {
            $comparison = $value <=> $right[$index];
            if ($comparison !== 0) return $comparison;
        }
        return 0;
    }
}
