<?php

namespace App\Support;

use App\Domain\Identity\Models\Role;
use App\Domain\Identity\Models\User;
use App\Domain\League\Models\League;
use App\Domain\League\Models\LeagueMembership;
use Illuminate\Http\Request;

class LeagueContext
{
    public const LEAGUE_KEY = 'active_league_id';
    public const ROLE_KEY = 'active_role_id';

    public function current(Request $request): ?array
    {
        $leagueId = (int) $request->session()->get(self::LEAGUE_KEY, 0);
        $roleId = (int) $request->session()->get(self::ROLE_KEY, 0);
        $user = $request->user();

        if (! $user || ! $leagueId || ! $roleId) {
            return null;
        }

        $league = League::whereKey($leagueId)->where('status', 'active')->first();
        $role = Role::whereKey($roleId)->where('scope', 'league')->first();
        if (! $league || ! $role) {
            $this->clear($request);
            return null;
        }

        if (! $user->hasRole('system_admin') && ! $this->hasActiveMembership($user, $leagueId, $roleId)) {
            $this->clear($request);
            return null;
        }

        return ['league' => $league, 'role' => $role];
    }

    public function activate(Request $request, League $league, Role $role): bool
    {
        $user = $request->user();
        if (! $league->isActive() || $role->scope !== 'league') {
            return false;
        }

        if (! $user->hasRole('system_admin') && ! $this->hasActiveMembership($user, $league->id, $role->id)) {
            return false;
        }

        $request->session()->put([
            self::LEAGUE_KEY => $league->id,
            self::ROLE_KEY => $role->id,
        ]);

        return true;
    }

    public function clear(Request $request): void
    {
        $request->session()->forget([self::LEAGUE_KEY, self::ROLE_KEY]);
    }

    private function hasActiveMembership(User $user, int $leagueId, int $roleId): bool
    {
        return LeagueMembership::query()
            ->where('user_id', $user->id)
            ->where('league_id', $leagueId)
            ->where('role_id', $roleId)
            ->where('status', 'active')
            ->exists();
    }
}
