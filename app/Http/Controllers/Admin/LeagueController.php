<?php

namespace App\Http\Controllers\Admin;

use App\Domain\Identity\Models\Role;
use App\Domain\Identity\Models\User;
use App\Domain\League\Enums\MembershipStatus;
use App\Domain\League\Enums\LeagueStatus;
use App\Domain\League\Models\League;
use App\Domain\League\Models\LeagueMembership;
use App\Http\Controllers\Controller;
use App\Http\Requests\StoreLeagueRequest;
use App\Http\Requests\UpdateLeagueRequest;
use App\Support\AuditLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

class LeagueController extends Controller
{
    public function __construct(private readonly AuditLogger $audit)
    {
    }

    public function index(Request $request): Response
    {
        $search = $request->string('search')->trim()->toString();
        $leagues = League::query()
            ->withCount(['memberships as active_admins_count' => fn ($query) => $query
                ->where('status', 'active')
                ->whereHas('role', fn ($roleQuery) => $roleQuery->where('slug', 'league_admin'))])
            ->when($search, function ($query) use ($search): void {
                $term = mb_strtolower($search);
                $query->whereRaw('LOWER(name) LIKE ?', ["%{$term}%"]);
            })
            ->latest()
            ->paginate(12)
            ->withQueryString();

        return Inertia::render('Admin/Leagues/Index', [
            'leagues' => $leagues,
            'filters' => ['search' => $search],
            'statuses' => $this->statuses(),
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('Admin/Leagues/Create', [
            'users' => User::where('status', 'active')->orderBy('name')->get(['id', 'name', 'email']),
            'statuses' => $this->statuses(),
        ]);
    }

    public function store(StoreLeagueRequest $request): RedirectResponse
    {
        DB::transaction(function () use ($request): void {
            $data = $request->safe()->except(['initial_admin_user_id']);
            $data['slug'] = ($data['slug'] ?? null) ?: $this->uniqueSlug($data['name']);
            $data['created_by'] = $request->user()->id;
            $league = League::create($data);
            $role = Role::where('slug', 'league_admin')->firstOrFail();
            $membership = LeagueMembership::create([
                'league_id' => $league->id,
                'user_id' => $request->integer('initial_admin_user_id'),
                'role_id' => $role->id,
                'status' => MembershipStatus::Active,
                'assigned_by' => $request->user()->id,
                'started_at' => now(),
            ]);

            $this->audit->log($request, 'league.created', $league, $league, [], $league->only(['name', 'slug', 'primary_color', 'secondary_color', 'status']));
            $this->audit->log($request, 'league.membership.created', $membership, $league, [], $membership->only(['user_id', 'role_id', 'status']));
        });

        return redirect()->route('admin.leagues.index')->with('success', 'Liga creada y administrador inicial asignado.');
    }

    public function edit(League $league): Response
    {
        return Inertia::render('Admin/Leagues/Edit', [
            'league' => $league,
            'statuses' => $this->statuses(),
        ]);
    }

    public function update(UpdateLeagueRequest $request, League $league): RedirectResponse
    {
        $oldValues = $league->only(['name', 'slug', 'primary_color', 'secondary_color', 'status']);
        $league->update($request->safe()->except(['reason']));
        $this->audit->log(
            $request,
            'league.updated',
            $league,
            $league,
            $oldValues,
            $league->fresh()->only(array_keys($oldValues)),
            $request->string('reason')->toString(),
        );

        return redirect()->route('admin.leagues.index')->with('success', 'Liga actualizada correctamente.');
    }

    public function destroy(Request $request, League $league): RedirectResponse
    {
        $validated = $request->validate(['reason' => ['required', 'string', 'max:500']]);
        $this->audit->log($request, 'league.deleted', $league, $league, $league->only(['name', 'slug', 'status']), [], $validated['reason']);
        $league->delete();

        return redirect()->route('admin.leagues.index')->with('success', 'Liga eliminada de forma lógica; su historial se conserva.');
    }

    private function uniqueSlug(string $name): string
    {
        $base = Str::slug($name) ?: 'liga';
        $slug = $base;
        $suffix = 2;
        while (League::withTrashed()->where('slug', $slug)->exists()) {
            $slug = "{$base}-{$suffix}";
            $suffix++;
        }
        return $slug;
    }

    private function statuses(): array
    {
        return collect(LeagueStatus::cases())->map(fn (LeagueStatus $status) => [
            'value' => $status->value,
            'label' => $status->label(),
        ])->all();
    }
}
