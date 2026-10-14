<?php

namespace App\Http\Controllers\League;

use App\Domain\Competition\Models\Competition;
use App\Domain\Competition\Models\Season;
use App\Domain\Discipline\Models\DisciplinePolicy;
use App\Domain\Discipline\Models\Sanction;
use App\Domain\Discipline\Models\SanctionAppeal;
use App\Domain\Discipline\Services\DisciplineService;
use App\Domain\League\Models\League;
use App\Domain\Player\Models\Player;
use App\Domain\Scheduling\Models\GameMatch;
use App\Domain\Team\Models\Team;
use App\Domain\Team\Models\TeamParticipation;
use App\Domain\Team\Models\TeamRepresentative;
use App\Http\Controllers\Controller;
use App\Http\Requests\ResolveSanctionAppealRequest;
use App\Http\Requests\StoreSanctionAppealRequest;
use App\Http\Requests\StoreSanctionRequest;
use App\Http\Requests\UpdateDisciplinePolicyRequest;
use App\Support\AuditLogger;
use App\Support\LeagueContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class DisciplineController extends Controller
{
    public function __construct(private readonly LeagueContext $context, private readonly DisciplineService $discipline, private readonly AuditLogger $audit) {}

    public function index(Request $request): Response
    {
        $league = $this->league($request);
        $canManage = $this->hasPermission($request, $league, 'discipline.manage');
        $canAppeal = $this->hasPermission($request, $league, 'discipline.appeal');
        $ownedTeamIds = TeamRepresentative::where('user_id', $request->user()->id)->where('status', 'active')->whereHas('team', fn ($query) => $query->where('league_id', $league->id))->pluck('team_id');
        $playerIds = Player::where('league_id', $league->id)->where('user_id', $request->user()->id)->pluck('id');

        $sanctions = Sanction::query()->where('league_id', $league->id)
            ->when(! $canManage && ! $canAppeal, fn ($query) => $query->where(function ($scope) use ($playerIds): void { $scope->whereIn('player_id', $playerIds); }))
            ->when(! $canManage && $canAppeal, fn ($query) => $query->where(function ($scope) use ($ownedTeamIds): void {
                $scope->whereIn('team_id', $ownedTeamIds)->orWhereHas('player.registrations', fn ($registrations) => $registrations->whereIn('team_id', $ownedTeamIds)->whereIn('status', ['active', 'suspended']));
            }))
            ->with(['season:id,name', 'competition:id,name', 'team:id,name', 'player:id,full_name', 'match.matchday:id,name', 'appeal.appellant:id,name', 'appeal.reviewer:id,name', 'servedMatches.match.matchday:id,name'])
            ->latest('issued_at')->paginate(20)->withQueryString()
            ->through(fn (Sanction $sanction) => $this->presentSanction($sanction, $request, $ownedTeamIds, $canManage, $canAppeal));

        return Inertia::render('League/Discipline/Index', [
            'policy' => DisciplinePolicy::firstOrCreate(['league_id' => $league->id]),
            'sanctions' => $sanctions,
            'permissions' => ['manage' => $canManage, 'appeal' => $canAppeal],
            'players' => $canManage ? Player::where('league_id', $league->id)->orderBy('full_name')->get(['id', 'full_name']) : [],
            'teams' => $canManage ? Team::where('league_id', $league->id)->orderBy('name')->get(['id', 'name']) : [],
            'seasons' => $canManage ? Season::where('league_id', $league->id)->orderByDesc('starts_on')->get(['id', 'name']) : [],
            'competitions' => $canManage ? Competition::whereHas('tournament.season', fn ($query) => $query->where('league_id', $league->id))->with('tournament.season:id,name')->orderBy('name')->get(['id', 'tournament_id', 'name']) : [],
            'matches' => $canManage ? GameMatch::whereHas('competition.tournament.season', fn ($query) => $query->where('league_id', $league->id))->with(['matchday:id,name', 'homeParticipation:id,registered_name', 'awayParticipation:id,registered_name'])->latest('scheduled_at')->limit(100)->get(['id', 'matchday_id', 'home_team_participation_id', 'away_team_participation_id', 'scheduled_at']) : [],
        ]);
    }

    public function updatePolicy(UpdateDisciplinePolicyRequest $request): RedirectResponse
    {
        $league = $this->league($request); $this->assertManage($request, $league);
        $policy = DisciplinePolicy::firstOrCreate(['league_id' => $league->id]); $old = $policy->toArray();
        $policy->update([...$request->safe()->except('reason'), 'updated_by' => $request->user()->id]);
        $this->audit->log($request, 'discipline.policy.updated', $policy, $league, $old, $policy->fresh()->toArray(), $request->validated('reason'));
        return back()->with('success', 'Configuración disciplinaria actualizada.');
    }

    public function store(StoreSanctionRequest $request): RedirectResponse
    {
        $league = $this->league($request); $this->assertManage($request, $league); $data = $request->validated();
        $this->assertReferences($data, $league);
        $sanction = $this->discipline->createManual($data, $league->id, $request->user()->id);
        $this->audit->log($request, 'discipline.sanction.created', $sanction, $league, [], $sanction->toArray(), $sanction->reason);
        return back()->with('success', 'Sanción registrada y notificada dentro del sistema.');
    }

    public function sync(Request $request): RedirectResponse
    {
        $league = $this->league($request); $this->assertManage($request, $league);
        $created = $this->discipline->syncLeague($league->id);
        foreach ($created as $sanction) $this->audit->log($request, 'discipline.sanction.automatic', $sanction, $league, [], $sanction->toArray(), $sanction->reason);
        return back()->with('success', $created->isEmpty() ? 'Las cédulas aprobadas ya estaban sincronizadas.' : "Se generaron {$created->count()} sanciones automáticas.");
    }

    public function appeal(StoreSanctionAppealRequest $request, Sanction $sanction): RedirectResponse
    {
        $league = $this->league($request); $this->assertSanction($sanction, $league);
        abort_unless($this->hasPermission($request, $league, 'discipline.appeal') && $this->representativeCanAppeal($request, $sanction), 403);
        if ($sanction->appeal()->exists()) throw ValidationException::withMessages(['appeal' => 'Esta sanción ya tiene una apelación registrada.']);
        if (now()->greaterThan($sanction->appeal_deadline_at)) throw ValidationException::withMessages(['appeal' => 'El plazo configurado para apelar ya venció.']);
        if ($sanction->status === 'revoked') throw ValidationException::withMessages(['appeal' => 'Una sanción revocada no puede apelarse.']);
        $file = $request->file('evidence');
        $appeal = $sanction->appeal()->create([
            'appellant_user_id' => $request->user()->id, 'statement' => $request->validated('statement'),
            'evidence_path' => $file?->store('discipline/appeals', 'local'), 'evidence_original_name' => $file?->getClientOriginalName(), 'evidence_mime' => $file?->getMimeType(),
            'status' => 'submitted', 'submitted_at' => now(),
        ]);
        $this->audit->log($request, 'discipline.appeal.submitted', $appeal, $league, [], $appeal->toArray(), $appeal->statement);
        return back()->with('success', 'Apelación registrada dentro del plazo.');
    }

    public function resolve(ResolveSanctionAppealRequest $request, SanctionAppeal $appeal): RedirectResponse
    {
        $league = $this->league($request); $appeal->loadMissing('sanction'); $this->assertSanction($appeal->sanction, $league); $this->assertManage($request, $league);
        $old = $appeal->sanction->toArray();
        $this->discipline->resolveAppeal($appeal->sanction, $request->validated('action'), $request->validated(), $request->user()->id);
        $updated = $appeal->sanction->fresh();
        $this->audit->log($request, 'discipline.appeal.resolved', $updated, $league, $old, $updated->toArray(), $request->validated('resolution'));
        return back()->with('success', 'Apelación resuelta y sanción actualizada.');
    }

    public function evidence(Request $request, SanctionAppeal $appeal): StreamedResponse
    {
        $league = $this->league($request); $appeal->loadMissing('sanction'); $this->assertSanction($appeal->sanction, $league);
        $allowed = $this->hasPermission($request, $league, 'discipline.manage') || $appeal->appellant_user_id === $request->user()->id || $this->representativeCanAppeal($request, $appeal->sanction);
        abort_unless($allowed && $appeal->evidence_path && Storage::disk('local')->exists($appeal->evidence_path), 404);
        return Storage::disk('local')->download($appeal->evidence_path, $appeal->evidence_original_name ?: 'evidencia');
    }

    private function presentSanction(Sanction $sanction, Request $request, $ownedTeamIds, bool $canManage, bool $canAppeal): array
    {
        $data = $sanction->toArray();
        $data['can_appeal'] = $canAppeal && ! $sanction->appeal && $sanction->status !== 'revoked' && now()->lessThanOrEqualTo($sanction->appeal_deadline_at) && ($ownedTeamIds->contains($sanction->team_id) || $sanction->player?->registrations()->whereIn('team_id', $ownedTeamIds)->whereIn('status', ['active', 'suspended'])->exists());
        $data['can_resolve'] = $canManage && $sanction->appeal?->status === 'submitted';
        if ($sanction->appeal) $data['appeal']['has_evidence'] = (bool) $sanction->appeal->evidence_path;
        return $data;
    }

    private function representativeCanAppeal(Request $request, Sanction $sanction): bool
    {
        $teamIds = TeamRepresentative::where('user_id', $request->user()->id)->where('status', 'active')->pluck('team_id');
        return $teamIds->contains($sanction->team_id) || ($sanction->player_id && $sanction->player()->whereHas('registrations', fn ($query) => $query->whereIn('team_id', $teamIds)->whereIn('status', ['active', 'suspended']))->exists());
    }

    private function assertReferences(array $data, League $league): void
    {
        if (! empty($data['player_id'])) abort_unless(Player::where('league_id', $league->id)->whereKey($data['player_id'])->exists(), 404);
        if (! empty($data['team_id'])) abort_unless(Team::where('league_id', $league->id)->whereKey($data['team_id'])->exists(), 404);
        if (! empty($data['season_id'])) abort_unless(Season::where('league_id', $league->id)->whereKey($data['season_id'])->exists(), 404);
        if (! empty($data['competition_id'])) abort_unless(Competition::whereKey($data['competition_id'])->whereHas('tournament.season', fn ($query) => $query->where('league_id', $league->id))->exists(), 404);
        if (! empty($data['match_id'])) abort_unless(GameMatch::whereKey($data['match_id'])->whereHas('competition.tournament.season', fn ($query) => $query->where('league_id', $league->id))->exists(), 404);
        if (($data['kind'] ?? null) === 'points_deduction' && ($data['subject_type'] ?? null) !== 'team') {
            throw ValidationException::withMessages(['subject_type' => 'El descuento de puntos debe aplicarse a un equipo.']);
        }
        if (! empty($data['season_id']) && ! empty($data['competition_id'])) {
            $sameSeason = Competition::whereKey($data['competition_id'])->whereHas('tournament', fn ($query) => $query->where('season_id', $data['season_id']))->exists();
            if (! $sameSeason) throw ValidationException::withMessages(['competition_id' => 'La competencia no pertenece a la temporada seleccionada.']);
        }
        if (($data['kind'] ?? null) === 'points_deduction' && ! empty($data['team_id']) && ! empty($data['competition_id'])) {
            $participates = TeamParticipation::where('team_id', $data['team_id'])->where('competition_id', $data['competition_id'])->exists();
            if (! $participates) throw ValidationException::withMessages(['team_id' => 'El equipo no participa en la competencia seleccionada.']);
        }
    }

    private function assertManage(Request $request, League $league): void { abort_unless($this->hasPermission($request, $league, 'discipline.manage'), 403); }
    private function assertSanction(Sanction $sanction, League $league): void { abort_unless($sanction->league_id === $league->id, 404); }
    private function hasPermission(Request $request, League $league, string $permission): bool { $roleId = (int) $request->session()->get(LeagueContext::ROLE_KEY); return $request->user()->hasPermission($permission) || $request->user()->hasLeaguePermission($permission, $league->id, $roleId); }
    private function league(Request $request): League { return $this->context->current($request)['league']; }
}
