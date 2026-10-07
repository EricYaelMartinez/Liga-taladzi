<?php

namespace App\Http\Controllers\League;

use App\Domain\Competition\Models\Competition;
use App\Domain\League\Models\League;
use App\Domain\Referee\Models\Referee;
use App\Domain\Scheduling\Models\GameMatch;
use App\Domain\Scheduling\Models\Matchday;
use App\Domain\Scheduling\Models\PlayingField;
use App\Domain\Scheduling\Services\MatchSchedulingService;
use App\Domain\Scheduling\Services\RoundRobinScheduleGenerator;
use App\Domain\Team\Models\TeamParticipation;
use App\Http\Controllers\Controller;
use App\Http\Requests\GenerateScheduleRequest;
use App\Http\Requests\ProgramMatchRequest;
use App\Http\Requests\StoreMatchdayRequest;
use App\Http\Requests\StoreScheduledMatchRequest;
use App\Http\Requests\TransitionMatchStatusRequest;
use App\Support\AuditLogger;
use App\Support\LeagueContext;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class ScheduleController extends Controller
{
    public function __construct(
        private readonly LeagueContext $context,
        private readonly AuditLogger $audit,
        private readonly RoundRobinScheduleGenerator $generator,
        private readonly MatchSchedulingService $scheduler,
    ) {}

    public function index(Request $request): Response
    {
        $league = $this->league($request);
        $canManage = $this->canManage($request, $league);
        $competitions = Competition::query()
            ->whereHas('tournament.season', fn ($query) => $query->where('league_id', $league->id))
            ->with([
                'tournament.season:id,league_id,name', 'division:id,name', 'category:id,name',
                'teamParticipations' => fn ($query) => $query->where('status', 'active')->with('team:id,name,short_name')->orderBy('registered_name'),
            ])->orderBy('name')->get();

        $matchdays = Matchday::query()
            ->whereHas('competition.tournament.season', fn ($query) => $query->where('league_id', $league->id))
            ->when(! $canManage, fn ($query) => $query->where('status', 'published'))
            ->with([
                'competition:id,tournament_id,division_id,category_id,name,format',
                'matches' => fn ($query) => $query
                    ->when(! $canManage, fn ($matches) => $matches->whereNot('status', 'draft'))
                    ->with([
                        'homeParticipation.team:id,name,short_name', 'awayParticipation.team:id,name,short_name',
                        'field.venue:id,name', 'refereeAssignments.referee.user:id,name',
                    ])->orderByRaw('scheduled_at IS NULL')->orderBy('scheduled_at')->orderBy('id'),
            ])->orderBy('competition_id')->orderBy('number')->get();

        return Inertia::render('League/Schedule/Index', [
            'competitions' => $competitions,
            'matchdays' => $matchdays,
            'fields' => PlayingField::where('status', 'active')->whereHas('venue', fn ($query) => $query->where('league_id', $league->id)->where('status', 'active'))->with('venue:id,name')->orderBy('name')->get(),
            'referees' => Referee::where('league_id', $league->id)->where('status', 'active')->with('user:id,name')->orderBy('category_level')->get(),
            'canManage' => $canManage,
        ]);
    }

    public function generate(GenerateScheduleRequest $request, Competition $competition): RedirectResponse
    {
        $league = $this->league($request);
        $this->assertCompetition($competition, $league);
        $created = $this->generator->generate($competition->load('regulation'), Carbon::parse($request->validated('first_match_date')), $request->integer('days_between_matchdays'), $request->user()->id);
        $this->audit->log($request, 'schedule.generated', $competition, $league, [], ['matchdays' => count($created)], $request->string('reason')->toString());
        return back()->with('success', count($created).' jornadas generadas en borrador. Ahora programa campo, horario y árbitro.');
    }

    public function storeMatchday(StoreMatchdayRequest $request): RedirectResponse
    {
        $league = $this->league($request);
        $competition = Competition::findOrFail($request->integer('competition_id'));
        $this->assertCompetition($competition, $league);
        if ($competition->matchdays()->where('number', $request->integer('number'))->exists()) {
            throw ValidationException::withMessages(['number' => 'Ya existe una jornada con este número en la competencia.']);
        }
        $matchday = $competition->matchdays()->create([
            ...$request->safe()->except(['competition_id', 'reason']),
            'status' => 'draft', 'created_by' => $request->user()->id,
        ]);
        $this->audit->log($request, 'schedule.matchday.created', $matchday, $league, [], $matchday->toArray(), $request->string('reason')->toString());
        return back()->with('success', 'Jornada creada en borrador.');
    }

    public function storeMatch(StoreScheduledMatchRequest $request, Matchday $matchday): RedirectResponse
    {
        $league = $this->league($request);
        $this->assertMatchday($matchday, $league);
        if ($matchday->status->value !== 'draft') throw ValidationException::withMessages(['matchday' => 'Solo puedes agregar partidos a una jornada en borrador.']);
        $home = $this->participation($matchday, $request->integer('home_team_participation_id'));
        $away = $this->participation($matchday, $request->integer('away_team_participation_id'));
        if ($home->id === $away->id) throw ValidationException::withMessages(['away_team_participation_id' => 'El equipo local y visitante deben ser distintos.']);
        $alreadyIncluded = $matchday->matches()->where(fn ($query) => $query
            ->whereIn('home_team_participation_id', [$home->id, $away->id])
            ->orWhereIn('away_team_participation_id', [$home->id, $away->id]))->exists();
        if ($alreadyIncluded) throw ValidationException::withMessages(['home_team_participation_id' => 'Uno de los equipos ya tiene partido en esta jornada.']);

        $match = $matchday->matches()->create([
            'competition_id' => $matchday->competition_id,
            'home_team_participation_id' => $home->id,
            'away_team_participation_id' => $away->id,
            'duration_minutes' => $this->generator->durationMinutes($matchday->competition->load('regulation')),
            'status' => 'draft', 'public_notes' => $request->validated('public_notes'), 'created_by' => $request->user()->id,
        ]);
        $this->audit->log($request, 'schedule.match.created', $match, $league, [], $match->toArray(), $request->string('reason')->toString());
        return back()->with('success', 'Partido agregado a la jornada.');
    }

    public function program(ProgramMatchRequest $request, GameMatch $match): RedirectResponse
    {
        $league = $this->league($request);
        $this->assertMatch($match, $league);
        $data = [
            ...$request->safe()->except(['central_referee_id', 'assistant_1_referee_id', 'assistant_2_referee_id', 'fourth_referee_id']),
            'referees' => [
                'central' => $request->integer('central_referee_id'),
                'assistant_1' => $request->filled('assistant_1_referee_id') ? $request->integer('assistant_1_referee_id') : null,
                'assistant_2' => $request->filled('assistant_2_referee_id') ? $request->integer('assistant_2_referee_id') : null,
                'fourth' => $request->filled('fourth_referee_id') ? $request->integer('fourth_referee_id') : null,
            ],
        ];
        $old = $match->load('refereeAssignments')->toArray();
        $updated = $this->scheduler->schedule($match, $data, $request->user());
        $this->audit->log($request, 'schedule.match.programmed', $updated, $league, $old, $updated->load('refereeAssignments')->toArray(), $request->string('reason')->toString());
        return back()->with('success', 'Partido programado y conflictos verificados.');
    }

    public function publish(Request $request, Matchday $matchday): RedirectResponse
    {
        $data = $request->validate(['reason' => ['required', 'string', 'max:500']]);
        $league = $this->league($request);
        $this->assertMatchday($matchday, $league);
        if ($matchday->status->value !== 'draft') throw ValidationException::withMessages(['matchday' => 'Solo puede publicarse una jornada en borrador.']);
        $matches = $matchday->matches()->with('refereeAssignments')->get();
        if ($matches->isEmpty()) throw ValidationException::withMessages(['matchday' => 'La jornada debe contener al menos un partido.']);
        foreach ($matches as $match) {
            if (! $match->scheduled_at || ! $match->playing_field_id || ! $match->refereeAssignments->contains('role', 'central')) {
                throw ValidationException::withMessages(['matchday' => 'Todos los partidos deben tener horario, cancha y árbitro central antes de publicar.']);
            }
        }
        DB::transaction(function () use ($matchday): void {
            $lockedMatchday = Matchday::whereKey($matchday->id)->lockForUpdate()->firstOrFail();
            if ($lockedMatchday->status->value !== 'draft') {
                throw ValidationException::withMessages(['matchday' => 'La jornada dejó de estar en borrador antes de completar la publicación.']);
            }
            $lockedMatches = $lockedMatchday->matches()->with('refereeAssignments')->lockForUpdate()->get();
            if ($lockedMatches->isEmpty() || $lockedMatches->contains(fn (GameMatch $match) => ! $match->scheduled_at
                || ! $match->playing_field_id || ! $match->refereeAssignments->contains('role', 'central'))) {
                throw ValidationException::withMessages(['matchday' => 'La programación cambió. Revisa que todos los partidos tengan horario, cancha y árbitro central.']);
            }
            $lockedMatchday->update(['status' => 'published', 'published_at' => now()]);
            GameMatch::whereIn('id', $lockedMatches->pluck('id'))->update(['status' => 'scheduled']);
        });
        $this->audit->log($request, 'schedule.matchday.published', $matchday, $league, ['status' => 'draft'], ['status' => 'published'], $data['reason']);
        return back()->with('success', 'Jornada publicada y disponible para consulta.');
    }

    public function transition(TransitionMatchStatusRequest $request, GameMatch $match): RedirectResponse
    {
        $league = $this->league($request);
        $this->assertMatch($match, $league);
        $target = $request->validated('status');
        $allowed = [
            'scheduled' => ['in_progress', 'postponed', 'cancelled'],
            'in_progress' => ['suspended', 'cancelled'],
            'suspended' => ['postponed', 'cancelled'],
            'postponed' => ['cancelled'],
        ];
        if (! in_array($target, $allowed[$match->status->value] ?? [], true)) {
            throw ValidationException::withMessages(['status' => 'La transición de estado solicitada no está permitida.']);
        }
        $old = ['status' => $match->status->value];
        $match->update(['status' => $target]);
        $match->scheduleChanges()->create([
            'type' => $target === 'cancelled' ? 'cancelled' : 'status_changed',
            'old_values' => $old, 'new_values' => ['status' => $target],
            'reason' => $request->string('reason')->toString(), 'performed_by' => $request->user()->id, 'occurred_at' => now(),
        ]);
        $this->audit->log($request, 'schedule.match.status_changed', $match, $league, $old, ['status' => $target], $request->string('reason')->toString());
        return back()->with('success', 'Estado del partido actualizado.');
    }

    private function league(Request $request): League { return $this->context->current($request)['league']; }

    private function canManage(Request $request, League $league): bool
    {
        $roleId = (int) $request->session()->get(LeagueContext::ROLE_KEY);
        return $request->user()->hasPermission('schedule.manage') || $request->user()->hasLeaguePermission('schedule.manage', $league->id, $roleId);
    }

    private function assertCompetition(Competition $competition, League $league): void
    {
        $competition->loadMissing('tournament.season');
        abort_unless($competition->tournament->season->league_id === $league->id, 404);
    }

    private function assertMatchday(Matchday $matchday, League $league): void
    {
        $matchday->loadMissing('competition.tournament.season');
        $this->assertCompetition($matchday->competition, $league);
    }

    private function assertMatch(GameMatch $match, League $league): void
    {
        $match->loadMissing('competition.tournament.season', 'matchday');
        $this->assertCompetition($match->competition, $league);
    }

    private function participation(Matchday $matchday, int $id): TeamParticipation
    {
        return TeamParticipation::whereKey($id)->where('competition_id', $matchday->competition_id)->where('status', 'active')->firstOrFail();
    }
}
