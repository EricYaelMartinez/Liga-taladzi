<?php

namespace Tests\Feature\League;

use App\Domain\Identity\Models\Role;
use App\Domain\Identity\Models\User;
use App\Domain\League\Models\League;
use App\Domain\League\Models\LeagueMembership;
use App\Domain\League\Models\LeagueSetting;
use App\Support\LeagueContext;
use Database\Seeders\IdentitySeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OperationalSettingsTest extends TestCase
{
    use RefreshDatabase;

    private Role $leagueAdminRole;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(IdentitySeeder::class);
        $this->leagueAdminRole = Role::where('slug', 'league_admin')->firstOrFail();
    }

    public function test_league_administrator_can_save_operational_settings(): void
    {
        [$administrator, $league] = $this->leagueAdministrator();

        $this->actingAs($administrator)
            ->withSession($this->contextSession($league, $this->leagueAdminRole))
            ->put('/liga/parametros', $this->validPayload())
            ->assertSessionHasNoErrors();

        $this->assertDatabaseHas('league_settings', [
            'league_id' => $league->id,
            'match_periods' => 2,
            'period_duration_minutes' => 40,
            'appeal_deadline_hours' => 2,
            'reactivation_window_days' => 21,
            'bond_enabled' => true,
            'bond_amount' => 1500,
            'revision' => 1,
        ]);
        $this->assertDatabaseHas('audit_logs', [
            'league_id' => $league->id,
            'action' => 'league.operational_settings.updated',
        ]);
    }

    public function test_subsequent_update_increments_revision_and_clears_disabled_bond(): void
    {
        [$administrator, $league] = $this->leagueAdministrator();
        LeagueSetting::create([
            'league_id' => $league->id,
            ...$this->storedSettings(),
            'revision' => 1,
        ]);
        $payload = $this->validPayload();
        $payload['bond_enabled'] = false;
        $payload['bond_amount'] = 5000;

        $this->actingAs($administrator)
            ->withSession($this->contextSession($league, $this->leagueAdminRole))
            ->put('/liga/parametros', $payload)
            ->assertSessionHasNoErrors();

        $settings = LeagueSetting::where('league_id', $league->id)->firstOrFail();
        $this->assertSame(2, $settings->revision);
        $this->assertFalse($settings->bond_enabled);
        $this->assertNull($settings->bond_amount);
    }

    public function test_bond_amount_is_required_only_when_bond_is_enabled(): void
    {
        [$administrator, $league] = $this->leagueAdministrator();
        $payload = $this->validPayload();
        $payload['bond_amount'] = null;

        $this->actingAs($administrator)
            ->withSession($this->contextSession($league, $this->leagueAdminRole))
            ->put('/liga/parametros', $payload)
            ->assertSessionHasErrors('bond_amount');

        $this->assertDatabaseMissing('league_settings', ['league_id' => $league->id]);
    }

    public function test_player_cannot_open_or_modify_league_settings(): void
    {
        $player = User::factory()->create();
        $league = $this->league();
        $playerRole = Role::where('slug', 'player')->firstOrFail();
        $this->membership($league, $player, $playerRole);

        $this->actingAs($player)
            ->withSession($this->contextSession($league, $playerRole))
            ->get('/liga/parametros')
            ->assertForbidden();

        $this->actingAs($player)
            ->withSession($this->contextSession($league, $playerRole))
            ->put('/liga/parametros', $this->validPayload())
            ->assertForbidden();
    }

    public function test_updating_one_league_does_not_change_another_league(): void
    {
        [$administrator, $league] = $this->leagueAdministrator();
        $otherLeague = League::create([
            'name' => 'Liga Independiente',
            'slug' => 'liga-independiente',
            'primary_color' => '#112233',
            'secondary_color' => '#445566',
            'status' => 'active',
        ]);
        LeagueSetting::create([
            'league_id' => $otherLeague->id,
            ...$this->storedSettings(),
            'period_duration_minutes' => 20,
            'revision' => 1,
        ]);

        $this->actingAs($administrator)
            ->withSession($this->contextSession($league, $this->leagueAdminRole))
            ->put('/liga/parametros', $this->validPayload())
            ->assertSessionHasNoErrors();

        $this->assertSame(40, LeagueSetting::where('league_id', $league->id)->value('period_duration_minutes'));
        $this->assertSame(20, LeagueSetting::where('league_id', $otherLeague->id)->value('period_duration_minutes'));
    }

    private function leagueAdministrator(): array
    {
        $administrator = User::factory()->create();
        $league = $this->league();
        $this->membership($league, $administrator, $this->leagueAdminRole);
        return [$administrator, $league];
    }

    private function league(): League
    {
        return League::create([
            'name' => 'Liga Taladzi',
            'slug' => 'liga-taladzi',
            'primary_color' => '#125444',
            'secondary_color' => '#d9a928',
            'status' => 'active',
        ]);
    }

    private function membership(League $league, User $user, Role $role): LeagueMembership
    {
        return LeagueMembership::create([
            'league_id' => $league->id,
            'user_id' => $user->id,
            'role_id' => $role->id,
            'status' => 'active',
            'started_at' => now(),
        ]);
    }

    private function contextSession(League $league, Role $role): array
    {
        return [LeagueContext::LEAGUE_KEY => $league->id, LeagueContext::ROLE_KEY => $role->id];
    }

    private function validPayload(): array
    {
        return [
            'match_periods' => 2,
            'period_duration_minutes' => 40,
            'halftime_minutes' => 10,
            'schedule_buffer_minutes' => 15,
            'appeal_deadline_hours' => 2,
            'payment_grace_days' => 14,
            'reactivation_window_days' => 21,
            'bond_enabled' => true,
            'bond_amount' => 1500,
            'currency' => 'MXN',
            'reason' => 'Configuración inicial aprobada',
        ];
    }

    private function storedSettings(): array
    {
        $payload = $this->validPayload();
        unset($payload['reason']);
        return $payload;
    }
}
