<?php

namespace App\Http\Controllers\League;

use App\Domain\Identity\Models\Role;
use App\Domain\Identity\Models\User;
use App\Domain\League\Enums\MembershipStatus;
use App\Domain\League\Models\LeagueMembership;
use App\Http\Controllers\Controller;
use App\Http\Requests\StoreLeagueMembershipRequest;
use App\Http\Requests\StoreLeagueUserRequest;
use App\Http\Requests\UpdateLeagueMembershipRequest;
use App\Support\AuditLogger;
use App\Support\LeagueContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class MembershipController extends Controller
{
    public function __construct(
        private readonly LeagueContext $context,
        private readonly AuditLogger $audit,
    ) {
    }

    public function index(Request $request): Response
    {
        $league = $this->context->current($request)['league'];

        return Inertia::render('League/Members/Index', [
            'memberships' => LeagueMembership::where('league_id', $league->id)
                ->with(['user:id,name,email,status', 'role:id,name,slug'])
                ->latest()
                ->get(),
            'roles' => Role::where('scope', 'league')->orderBy('name')->get(['id', 'name', 'slug']),
            'statuses' => collect(MembershipStatus::cases())->map(fn (MembershipStatus $status) => ['value' => $status->value, 'label' => $status->label()]),
        ]);
    }

    public function createUser(): Response
    {
        return Inertia::render('League/Members/CreateUser', [
            'roles' => Role::where('scope', 'league')->orderBy('name')->get(['id', 'name', 'slug']),
        ]);
    }

    public function storeUser(StoreLeagueUserRequest $request): RedirectResponse
    {
        $league = $this->context->current($request)['league'];

        DB::transaction(function () use ($request, $league): void {
            $user = User::create([
                ...$request->safe()->only(['name', 'email', 'phone', 'password']),
                'status' => 'active',
                'force_password_change' => true,
            ]);
            $membership = LeagueMembership::create([
                'league_id' => $league->id,
                'user_id' => $user->id,
                'role_id' => $request->integer('role_id'),
                'status' => MembershipStatus::Active,
                'assigned_by' => $request->user()->id,
                'started_at' => now(),
            ]);
            $this->audit->log($request, 'league.user.created', $user, $league, [], $user->only(['name', 'email', 'phone', 'status']), $request->string('reason')->toString());
            $this->audit->log($request, 'league.membership.created', $membership, $league, [], $membership->only(['user_id', 'role_id', 'status']), $request->string('reason')->toString());
        });

        return redirect()->route('league.members.index')->with('success', 'Usuario creado y rol asignado. Deberá cambiar su contraseña al ingresar.');
    }

    public function store(StoreLeagueMembershipRequest $request): RedirectResponse
    {
        $league = $this->context->current($request)['league'];
        $user = User::where('email', $request->validated()['email'])->where('status', 'active')->firstOrFail();
        $membership = LeagueMembership::firstOrNew([
            'league_id' => $league->id,
            'user_id' => $user->id,
            'role_id' => $request->integer('role_id'),
        ]);
        $oldValues = $membership->exists ? $membership->only(['status', 'ended_at']) : [];
        $membership->fill([
            'status' => MembershipStatus::Active,
            'assigned_by' => $request->user()->id,
            'started_at' => $membership->started_at ?? now(),
            'ended_at' => null,
        ])->save();

        $this->audit->log($request, $oldValues ? 'league.membership.reactivated' : 'league.membership.created', $membership, $league, $oldValues, $membership->only(['user_id', 'role_id', 'status']), $request->string('reason')->toString());

        return back()->with('success', 'Rol asignado correctamente.');
    }

    public function update(UpdateLeagueMembershipRequest $request, LeagueMembership $membership): RedirectResponse
    {
        $league = $this->context->current($request)['league'];
        abort_unless($membership->league_id === $league->id, 404);

        $newStatus = MembershipStatus::from($request->validated()['status']);
        if ($membership->role()->where('slug', 'league_admin')->exists()
            && $membership->isActive()
            && $newStatus !== MembershipStatus::Active
            && $this->activeAdministratorCount($league->id) <= 1) {
            return back()->withErrors(['status' => 'La liga debe conservar al menos un administrador activo.']);
        }

        $oldValues = $membership->only(['status', 'ended_at']);
        $membership->update([
            'status' => $newStatus,
            'ended_at' => $newStatus === MembershipStatus::Active ? null : now(),
        ]);
        $this->audit->log($request, 'league.membership.status_updated', $membership, $league, $oldValues, $membership->fresh()->only(['status', 'ended_at']), $request->string('reason')->toString());

        return back()->with('success', 'Estado del acceso actualizado.');
    }

    private function activeAdministratorCount(int $leagueId): int
    {
        return LeagueMembership::where('league_id', $leagueId)
            ->where('status', 'active')
            ->whereHas('role', fn ($query) => $query->where('slug', 'league_admin'))
            ->count();
    }
}
