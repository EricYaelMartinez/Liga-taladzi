<?php

namespace App\Http\Controllers;

use App\Domain\Competition\Models\Competition;
use App\Domain\Discipline\Models\Sanction;
use App\Domain\League\Models\League;
use App\Domain\Scheduling\Models\GameMatch;
use App\Domain\Statistics\Services\CompetitionStatisticsService;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;

class PublicPortalController extends Controller
{
    public function __construct(private readonly CompetitionStatisticsService $statistics) {}

    public function directory(): Response
    {
        $leagues = League::query()
            ->where('status', 'active')
            ->orderBy('name')
            ->get(['id', 'name', 'slug', 'logo_path', 'primary_color', 'secondary_color']);

        $competitionsByLeague = Competition::query()
            ->whereIn('status', ['active', 'finished'])
            ->whereHas('tournament', fn ($query) => $query
                ->whereIn('status', ['active', 'finished'])
                ->whereHas('season', fn ($season) => $season
                    ->whereIn('league_id', $leagues->pluck('id'))
                    ->whereIn('status', ['active', 'finished'])))
            ->with([
                'tournament:id,season_id,name,status',
                'tournament.season:id,league_id,name,status,starts_on,ends_on',
                'division:id,name',
                'category:id,name,gender',
            ])
            ->get()
            ->sortByDesc(fn (Competition $competition): string => sprintf(
                '%d-%s-%010d',
                $competition->status->value === 'active' ? 1 : 0,
                $competition->tournament->season->starts_on?->format('Y-m-d') ?? '',
                $competition->id,
            ))
            ->groupBy(fn (Competition $competition): int => $competition->tournament->season->league_id);

        $directory = $leagues->map(function (League $league) use ($competitionsByLeague): array {
            /** @var Collection<int, Competition> $competitions */
            $competitions = $competitionsByLeague->get($league->id, collect());
            $featured = $competitions->first();

            return [
                'id' => $league->id,
                'name' => $league->name,
                'slug' => $league->slug,
                'logo_url' => $league->logo_path ? Storage::disk('public')->url($league->logo_path) : null,
                'primary_color' => $league->primary_color,
                'secondary_color' => $league->secondary_color,
                'genders' => $competitions->pluck('category.gender')->filter()->unique()->values()->all(),
                'featured_competition' => $featured ? [
                    'name' => $featured->name,
                    'status' => $featured->status->value,
                    'division' => $featured->division->name,
                    'category' => $featured->category->name,
                    'gender' => $featured->category->gender,
                    'format' => $featured->format->value,
                    'season' => $featured->tournament->season->name,
                    'starts_on' => $featured->tournament->season->starts_on?->format('Y-m-d'),
                    'ends_on' => $featured->tournament->season->ends_on?->format('Y-m-d'),
                ] : null,
            ];
        });

        return Inertia::render('Public/LeagueDirectory', ['leagues' => $directory]);
    }

    public function show(Request $request, League $league): Response
    {
        abort_unless($league->status->value === 'active', 404);

        $competitions = Competition::query()
            ->whereHas('tournament', fn ($query) => $query
                ->whereIn('status', ['active', 'finished'])
                ->whereHas('season', fn ($season) => $season
                    ->where('league_id', $league->id)
                    ->whereIn('status', ['active', 'finished'])))
            ->whereIn('status', ['active', 'finished'])
            ->with([
                'tournament:id,season_id,name',
                'tournament.season:id,league_id,name,status,starts_on,ends_on',
                'division:id,name',
                'category:id,name',
            ])
            ->get()
            ->sortByDesc(fn (Competition $competition): string => sprintf(
                '%d-%s-%010d',
                $competition->status->value === 'active' ? 1 : 0,
                $competition->tournament->season->starts_on?->format('Y-m-d') ?? '',
                $competition->id,
            ))
            ->values();

        $selected = $this->selectCompetition($request, $competitions);
        $statistics = $selected ? $this->statistics->calculate($selected, publishedOnly: true) : null;

        return Inertia::render('Public/LeaguePortal', [
            'league' => [
                'name' => $league->name,
                'slug' => $league->slug,
                'logo_url' => $league->logo_path ? Storage::disk('public')->url($league->logo_path) : null,
                'primary_color' => $league->primary_color,
                'secondary_color' => $league->secondary_color,
            ],
            'competitions' => $competitions->map(fn (Competition $competition): array => [
                'id' => $competition->id,
                'name' => $competition->name,
                'status' => $competition->status->value,
                'season' => [
                    'id' => $competition->tournament->season->id,
                    'name' => $competition->tournament->season->name,
                    'status' => $competition->tournament->season->status->value,
                ],
                'tournament' => $competition->tournament->name,
                'division' => ['id' => $competition->division->id, 'name' => $competition->division->name],
                'category' => $competition->category->name,
            ]),
            'selectedCompetitionId' => $selected?->id,
            'schedule' => $selected ? $this->schedule($selected) : [],
            'statistics' => $statistics ? [
                'standings' => $statistics['standings'],
                'results' => collect($statistics['results'])->map(fn (array $result): array => [
                    'match_id' => $result['match_id'],
                    'matchday' => $result['matchday'],
                    'scheduled_at' => $result['scheduled_at'],
                    'home_team' => $result['home_team'],
                    'away_team' => $result['away_team'],
                    'home_score' => $result['home_score'],
                    'away_score' => $result['away_score'],
                    'home_no_show' => $result['home_no_show'],
                    'away_no_show' => $result['away_no_show'],
                ])->values(),
                'scorers' => $statistics['scorers'],
                'discipline' => $statistics['discipline'],
                'summary' => $statistics['summary'],
            ] : null,
            'activeSanctions' => $selected ? $this->activeSportingSanctions($league, $selected) : [],
        ]);
    }

    /** @param Collection<int, Competition> $competitions */
    private function selectCompetition(Request $request, Collection $competitions): ?Competition
    {
        if ($request->filled('competition_id')) {
            $selected = $competitions->firstWhere('id', $request->integer('competition_id'));
            abort_unless($selected, 404);
            return $selected;
        }

        return $competitions
            ->when($request->filled('season_id'), fn ($items) => $items->where('tournament.season.id', $request->integer('season_id')))
            ->when($request->filled('division_id'), fn ($items) => $items->where('division.id', $request->integer('division_id')))
            ->first();
    }

    private function schedule(Competition $competition): array
    {
        return GameMatch::query()
            ->where('competition_id', $competition->id)
            ->where('status', '!=', 'draft')
            ->whereHas('matchday', fn ($query) => $query->whereIn('status', ['published', 'completed']))
            ->with([
                'matchday:id,name,number,status,starts_on,ends_on',
                'homeParticipation:id,team_id,registered_name',
                'homeParticipation.team:id,crest_path',
                'awayParticipation:id,team_id,registered_name',
                'awayParticipation.team:id,crest_path',
                'field:id,venue_id,name',
                'field.venue:id,name,address',
                'report' => fn ($query) => $query->where('status', 'approved')->select(['id', 'match_id', 'home_score', 'away_score']),
            ])
            ->orderBy('scheduled_at')
            ->orderBy('id')
            ->get()
            ->map(fn (GameMatch $match): array => [
                'id' => $match->id,
                'matchday' => [
                    'id' => $match->matchday->id,
                    'name' => $match->matchday->name,
                    'number' => $match->matchday->number,
                ],
                'home_team' => $match->homeParticipation->registered_name,
                'home_crest_url' => $match->homeParticipation->team?->crest_path
                    ? Storage::disk('public')->url($match->homeParticipation->team->crest_path)
                    : null,
                'away_team' => $match->awayParticipation->registered_name,
                'away_crest_url' => $match->awayParticipation->team?->crest_path
                    ? Storage::disk('public')->url($match->awayParticipation->team->crest_path)
                    : null,
                'scheduled_at' => $match->scheduled_at?->toIso8601String(),
                'status' => $match->status->value,
                'field' => $match->field ? [
                    'name' => $match->field->name,
                    'venue' => $match->field->venue?->name,
                    'address' => $match->field->venue?->address,
                ] : null,
                'public_notes' => $match->public_notes,
                'score' => $match->report ? [
                    'home' => $match->report->home_score,
                    'away' => $match->report->away_score,
                ] : null,
            ])->all();
    }

    private function activeSportingSanctions(League $league, Competition $competition): array
    {
        return Sanction::query()
            ->where('league_id', $league->id)
            ->where('competition_id', $competition->id)
            ->where('status', 'active')
            ->whereIn('kind', ['suspension', 'disqualification'])
            ->with('team:id,name')
            ->latest('issued_at')
            ->get(['id', 'competition_id', 'team_id', 'subject_type', 'subject_name', 'kind', 'matches_total', 'matches_served', 'issued_at'])
            ->map(fn (Sanction $sanction): array => [
                'id' => $sanction->id,
                'subject_type' => $sanction->subject_type,
                'subject_name' => $sanction->subject_name,
                'team' => $sanction->team?->name,
                'kind' => $sanction->kind,
                'matches_remaining' => $sanction->kind === 'suspension'
                    ? max(0, $sanction->matches_total - $sanction->matches_served)
                    : null,
                'issued_at' => $sanction->issued_at?->toIso8601String(),
            ])->all();
    }
}
