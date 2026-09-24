<?php

namespace App\Http\Controllers;

use App\Domain\Identity\Models\Role;
use App\Domain\League\Models\League;
use App\Domain\League\Models\LeagueMembership;
use App\Support\LeagueContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class AccessContextController extends Controller
{
    public function __construct(private readonly LeagueContext $context)
    {
    }

    public function index(Request $request): Response
    {
        $user = $request->user();
        $options = LeagueMembership::query()
            ->where('user_id', $user->id)
            ->where('status', 'active')
            ->whereHas('league', fn ($query) => $query->where('status', 'active'))
            ->with(['league:id,name,logo_path,primary_color,secondary_color', 'role:id,name,slug'])
            ->orderBy('league_id')
            ->get()
            ->map(fn (LeagueMembership $membership) => [
                'league' => $membership->league,
                'role' => $membership->role,
            ])
            ->values();

        if ($user->hasRole('system_admin')) {
            $leagueAdminRole = Role::where('slug', 'league_admin')->firstOrFail();
            $systemLeagueOptions = League::where('status', 'active')
                ->orderBy('name')
                ->get(['id', 'name', 'logo_path', 'primary_color', 'secondary_color'])
                ->map(fn (League $league) => ['league' => $league, 'role' => $leagueAdminRole]);
            $options = $options
                ->concat($systemLeagueOptions)
                ->unique(fn (array $option) => $option['league']->id.'-'.$option['role']->id)
                ->sortBy('league.name')
                ->values();
        }

        return Inertia::render('Access/Select', [
            'canUseSystemAdministration' => $user->hasRole('system_admin'),
            'options' => $options,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'scope' => ['required', 'in:system,league'],
            'league_id' => ['nullable', 'integer', 'exists:leagues,id'],
            'role_id' => ['nullable', 'integer', 'exists:roles,id'],
        ]);

        if ($validated['scope'] === 'system') {
            abort_unless($request->user()->hasRole('system_admin'), 403);
            $this->context->clear($request);
            return redirect()->route('dashboard');
        }

        $league = League::findOrFail($validated['league_id'] ?? 0);
        $role = Role::findOrFail($validated['role_id'] ?? 0);
        abort_unless($this->context->activate($request, $league, $role), 403);

        return redirect()->route('dashboard')->with('success', "Acceso activo: {$league->name} · {$role->name}.");
    }
}
