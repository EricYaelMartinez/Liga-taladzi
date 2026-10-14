<?php

namespace App\Http\Controllers\League;

use App\Domain\Competition\Models\Competition;
use App\Domain\League\Models\League;
use App\Domain\Statistics\Services\CompetitionStatisticsService;
use App\Http\Controllers\Controller;
use App\Support\LeagueContext;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class StatisticsController extends Controller
{
    public function __construct(private readonly LeagueContext $context, private readonly CompetitionStatisticsService $statistics) {}

    public function __invoke(Request $request): Response
    {
        $league = $this->league($request);
        $competitions = Competition::query()
            ->whereHas('tournament.season', fn ($query) => $query->where('league_id', $league->id))
            ->with(['tournament.season:id,league_id,name,status,starts_on', 'division:id,name', 'category:id,name'])
            ->get()->sortByDesc(fn (Competition $competition) => sprintf('%s-%010d', $competition->tournament->season->starts_on?->format('Y-m-d') ?? '', $competition->id))->values();

        $selected = $request->filled('competition_id')
            ? $competitions->firstWhere('id', $request->integer('competition_id'))
            : $competitions->first();
        if ($request->filled('competition_id') && ! $selected) abort(404);

        return Inertia::render('League/Statistics/Index', [
            'competitions' => $competitions,
            'selectedCompetitionId' => $selected?->id,
            'statistics' => $selected ? $this->statistics->calculate($selected) : null,
        ]);
    }

    private function league(Request $request): League { return $this->context->current($request)['league']; }
}
