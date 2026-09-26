<?php

namespace App\Http\Controllers\League;

use App\Domain\Competition\Enums\RegulationStatus;
use App\Domain\Competition\Enums\SeasonStatus;
use App\Domain\Competition\Models\Category;
use App\Domain\Competition\Models\Competition;
use App\Domain\Competition\Models\Division;
use App\Domain\Competition\Models\Regulation;
use App\Domain\Competition\Models\Season;
use App\Domain\Competition\Models\Tournament;
use App\Domain\League\Models\League;
use App\Domain\League\Models\LeagueSetting;
use App\Http\Controllers\Controller;
use App\Http\Requests\StoreCategoryRequest;
use App\Http\Requests\StoreCompetitionRequest;
use App\Http\Requests\StoreDivisionRequest;
use App\Http\Requests\StoreRegulationRequest;
use App\Http\Requests\StoreSeasonRequest;
use App\Http\Requests\StoreTournamentRequest;
use App\Support\AuditLogger;
use App\Support\LeagueContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class CompetitionSetupController extends Controller
{
    private const SETTING_FIELDS = [
        'match_periods', 'period_duration_minutes', 'halftime_minutes', 'schedule_buffer_minutes',
        'appeal_deadline_hours', 'payment_grace_days', 'reactivation_window_days',
        'bond_enabled', 'bond_amount', 'currency', 'revision',
    ];

    public function __construct(
        private readonly LeagueContext $context,
        private readonly AuditLogger $audit,
    ) {
    }

    public function index(Request $request): Response
    {
        $league = $this->league($request);

        $seasons = Season::query()->where('league_id', $league->id)
            ->with(['tournaments' => fn ($query) => $query->withCount('competitions')->orderBy('name')])
            ->latest('starts_on')->get();
        $divisions = Division::query()->where('league_id', $league->id)->orderBy('sort_order')->orderBy('name')->get();
        $categories = Category::query()->where('league_id', $league->id)->orderBy('name')->get();
        $regulations = Regulation::query()->where('league_id', $league->id)->with('tiebreakers')->latest('id')->get();
        $competitions = Competition::query()
            ->whereHas('tournament.season', fn ($query) => $query->where('league_id', $league->id))
            ->with(['tournament.season', 'division', 'category', 'regulation.tiebreakers'])
            ->latest('id')->get();

        return Inertia::render('League/Competitions/Index', [
            'hasOperationalSettings' => LeagueSetting::where('league_id', $league->id)->exists(),
            'seasons' => $seasons,
            'divisions' => $divisions,
            'categories' => $categories,
            'regulations' => $regulations,
            'competitions' => $competitions,
        ]);
    }

    public function storeSeason(StoreSeasonRequest $request): RedirectResponse
    {
        $league = $this->league($request);
        $seasonName = $request->string('name')->toString();
        $this->rejectDuplicate(Season::where('league_id', $league->id)->whereRaw('LOWER(name) = ?', [Str::lower($seasonName)]), 'name', 'Ya existe una temporada con este nombre.');

        $season = Season::create([...$request->validated(), 'league_id' => $league->id, 'created_by' => $request->user()->id]);
        $this->audit->log($request, 'competition.season.created', $season, $league, [], $season->toArray());

        return back()->with('success', 'Temporada creada correctamente.');
    }

    public function transitionSeason(Request $request, Season $season): RedirectResponse
    {
        $league = $this->league($request);
        $this->assertSeason($season, $league);
        $data = $request->validate(['status' => ['required', Rule::enum(SeasonStatus::class)]]);
        $target = SeasonStatus::from($data['status']);
        $allowed = [
            'planning' => ['registration', 'active', 'cancelled'],
            'registration' => ['active', 'cancelled'],
            'active' => ['finished', 'cancelled'],
            'finished' => ['archived'],
            'archived' => [],
            'cancelled' => ['archived'],
        ];
        if (! in_array($target->value, $allowed[$season->status->value], true)) {
            throw ValidationException::withMessages(['status' => 'La transición de estado solicitada no está permitida.']);
        }

        $old = $season->status->value;
        $season->update(['status' => $target]);
        $this->audit->log($request, 'competition.season.status_changed', $season, $league, ['status' => $old], ['status' => $target->value]);

        return back()->with('success', 'Estado de la temporada actualizado.');
    }

    public function storeDivision(StoreDivisionRequest $request): RedirectResponse
    {
        $league = $this->league($request);
        $slug = Str::slug($request->string('name')->toString());
        $this->rejectDuplicate(Division::where('league_id', $league->id)->where('slug', $slug), 'name', 'Ya existe una división con este nombre.');
        $division = Division::create([...$request->validated(), 'league_id' => $league->id, 'slug' => $slug]);
        $this->audit->log($request, 'competition.division.created', $division, $league, [], $division->toArray());
        return back()->with('success', 'División creada correctamente.');
    }

    public function storeCategory(StoreCategoryRequest $request): RedirectResponse
    {
        $league = $this->league($request);
        $slug = Str::slug($request->string('name')->toString());
        $this->rejectDuplicate(Category::where('league_id', $league->id)->where('slug', $slug), 'name', 'Ya existe una categoría con este nombre.');
        $category = Category::create([...$request->validated(), 'league_id' => $league->id, 'slug' => $slug]);
        $this->audit->log($request, 'competition.category.created', $category, $league, [], $category->toArray());
        return back()->with('success', 'Categoría creada correctamente.');
    }

    public function storeTournament(StoreTournamentRequest $request): RedirectResponse
    {
        $league = $this->league($request);
        $season = Season::where('league_id', $league->id)->findOrFail($request->integer('season_id'));
        if (in_array($season->status, [SeasonStatus::Archived, SeasonStatus::Cancelled], true)) {
            throw ValidationException::withMessages(['season_id' => 'No se pueden agregar torneos a esta temporada.']);
        }
        $slug = Str::slug($request->string('name')->toString());
        $this->rejectDuplicate(Tournament::where('season_id', $season->id)->where('slug', $slug), 'name', 'Ya existe un torneo con este nombre en la temporada.');
        $tournament = Tournament::create([
            'season_id' => $season->id, 'name' => $request->string('name')->toString(), 'slug' => $slug,
            'status' => 'planning', 'created_by' => $request->user()->id,
        ]);
        $this->audit->log($request, 'competition.tournament.created', $tournament, $league, [], $tournament->toArray());
        return back()->with('success', 'Torneo creado correctamente.');
    }

    public function storeRegulation(StoreRegulationRequest $request): RedirectResponse
    {
        $league = $this->league($request);
        $settings = LeagueSetting::where('league_id', $league->id)->first();
        if (! $settings) {
            throw ValidationException::withMessages(['name' => 'Primero debes guardar los parámetros generales de la liga.']);
        }

        DB::transaction(function () use ($request, $league, $settings): void {
            League::whereKey($league->id)->lockForUpdate()->firstOrFail();
            $name = $request->string('name')->toString();
            $version = (int) Regulation::where('league_id', $league->id)
                ->whereRaw('LOWER(name) = ?', [Str::lower($name)])->max('version') + 1;
            $regulation = Regulation::create([
                ...$request->safe()->except('tiebreakers'),
                'league_id' => $league->id,
                'version' => $version,
                'status' => RegulationStatus::Draft,
                'league_settings_snapshot' => $settings->only(self::SETTING_FIELDS),
                'created_by' => $request->user()->id,
            ]);
            $this->replaceTiebreakers($regulation, $request->validated('tiebreakers'));
            $this->audit->log($request, 'competition.regulation.created', $regulation, $league, [], $regulation->load('tiebreakers')->toArray());
        });

        return back()->with('success', 'Borrador de reglamento creado correctamente.');
    }

    public function updateRegulation(StoreRegulationRequest $request, Regulation $regulation): RedirectResponse
    {
        $league = $this->league($request);
        $this->assertRegulation($regulation, $league);
        if (! $regulation->isMutable()) {
            throw ValidationException::withMessages(['name' => 'El reglamento publicado o en uso es inmutable. Crea una nueva versión.']);
        }

        DB::transaction(function () use ($request, $regulation, $league): void {
            $old = $regulation->load('tiebreakers')->toArray();
            $regulation->update($request->safe()->except('tiebreakers'));
            $this->replaceTiebreakers($regulation, $request->validated('tiebreakers'));
            $this->audit->log($request, 'competition.regulation.updated', $regulation, $league, $old, $regulation->load('tiebreakers')->toArray());
        });
        return back()->with('success', 'Reglamento actualizado correctamente.');
    }

    public function publishRegulation(Request $request, Regulation $regulation): RedirectResponse
    {
        $league = $this->league($request);
        $this->assertRegulation($regulation, $league);
        if (! $regulation->isMutable()) {
            throw ValidationException::withMessages(['regulation' => 'Solo se puede publicar un borrador.']);
        }
        $regulation->update(['status' => RegulationStatus::Published, 'published_at' => now()]);
        $this->audit->log($request, 'competition.regulation.published', $regulation, $league, ['status' => 'draft'], ['status' => 'published']);
        return back()->with('success', 'Reglamento publicado; su contenido quedó protegido.');
    }

    public function storeCompetition(StoreCompetitionRequest $request): RedirectResponse
    {
        $league = $this->league($request);
        $tournament = Tournament::whereHas('season', fn ($query) => $query->where('league_id', $league->id))->findOrFail($request->integer('tournament_id'));
        $division = Division::where('league_id', $league->id)->where('status', 'active')->findOrFail($request->integer('division_id'));
        $category = Category::where('league_id', $league->id)->where('status', 'active')->findOrFail($request->integer('category_id'));
        $regulation = Regulation::where('league_id', $league->id)->findOrFail($request->integer('regulation_id'));
        if (! in_array($regulation->status, [RegulationStatus::Published, RegulationStatus::InUse], true)) {
            throw ValidationException::withMessages(['regulation_id' => 'Debes seleccionar un reglamento publicado.']);
        }
        $duplicate = Competition::where('tournament_id', $tournament->id)->where('division_id', $division->id)->where('category_id', $category->id)->exists();
        if ($duplicate) throw ValidationException::withMessages(['division_id' => 'Ya existe una competencia para este torneo, división y categoría.']);

        DB::transaction(function () use ($request, $tournament, $division, $category, $regulation, $league): void {
            $competition = Competition::create([
                ...$request->safe()->except(['tournament_id', 'division_id', 'category_id', 'regulation_id']),
                'tournament_id' => $tournament->id,
                'division_id' => $division->id,
                'category_id' => $category->id,
                'regulation_id' => $regulation->id,
                'status' => SeasonStatus::Planning,
                'created_by' => $request->user()->id,
            ]);
            if ($regulation->status === RegulationStatus::Published) $regulation->update(['status' => RegulationStatus::InUse]);
            $this->audit->log($request, 'competition.competition.created', $competition, $league, [], $competition->toArray());
        });

        return back()->with('success', 'Competencia creada correctamente.');
    }

    private function league(Request $request): League { return $this->context->current($request)['league']; }

    private function assertSeason(Season $season, League $league): void
    {
        abort_unless($season->league_id === $league->id, 404);
    }

    private function assertRegulation(Regulation $regulation, League $league): void
    {
        abort_unless($regulation->league_id === $league->id, 404);
    }

    private function rejectDuplicate($query, string $field, string $message): void
    {
        if ($query->exists()) throw ValidationException::withMessages([$field => $message]);
    }

    private function replaceTiebreakers(Regulation $regulation, array $criteria): void
    {
        $regulation->tiebreakers()->delete();
        foreach (array_values($criteria) as $index => $criterion) {
            $regulation->tiebreakers()->create(['criterion' => $criterion, 'priority' => $index + 1]);
        }
    }
}
