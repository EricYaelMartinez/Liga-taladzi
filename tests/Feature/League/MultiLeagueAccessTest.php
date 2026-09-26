<?php

namespace Tests\Feature\League;

use App\Domain\Audit\Models\AuditLog;
use App\Domain\Identity\Models\Role;
use App\Domain\Identity\Models\User;
use App\Domain\League\Models\League;
use App\Domain\League\Models\LeagueMembership;
use App\Support\LeagueContext;
use Database\Seeders\IdentitySeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MultiLeagueAccessTest extends TestCase
{
    use RefreshDatabase;

    private Role $systemAdminRole;
    private Role $leagueAdminRole;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(IdentitySeeder::class);
        $this->systemAdminRole = Role::where('slug', 'system_admin')->firstOrFail();
        $this->leagueAdminRole = Role::where('slug', 'league_admin')->firstOrFail();
    }

    public function test_system_administrator_can_create_league_with_initial_administrator(): void
    {
        $systemAdmin = User::factory()->create();
        $systemAdmin->roles()->attach($this->systemAdminRole);
        $initialAdmin = User::factory()->create();

        $this->actingAs($systemAdmin)->post('/administracion/ligas', [
            'name' => 'Liga Municipal de Futbol Taladzi',
            'slug' => '',
            'primary_color' => '#125444',
            'secondary_color' => '#d9a928',
            'status' => 'active',
            'initial_admin_user_id' => $initialAdmin->id,
        ])->assertRedirect('/administracion/ligas');

        $league = League::where('slug', 'liga-municipal-de-futbol-taladzi')->firstOrFail();
        $this->assertDatabaseHas('league_memberships', [
            'league_id' => $league->id,
            'user_id' => $initialAdmin->id,
            'role_id' => $this->leagueAdminRole->id,
            'status' => 'active',
        ]);
        $this->assertDatabaseHas(AuditLog::class, ['league_id' => $league->id, 'action' => 'league.created']);
    }

    public function test_league_administrator_can_activate_assigned_access(): void
    {
        $user = User::factory()->create();
        $league = $this->league();
        $this->membership($league, $user, $this->leagueAdminRole);

        $this->actingAs($user)->post('/seleccionar-acceso', [
            'scope' => 'league',
            'league_id' => $league->id,
            'role_id' => $this->leagueAdminRole->id,
        ])->assertRedirect('/panel');

        $this->assertSame($league->id, session(LeagueContext::LEAGUE_KEY));
        $this->actingAs($user)->get('/liga/miembros')->assertOk();
    }

    public function test_user_cannot_activate_another_league_by_manipulating_request(): void
    {
        $user = User::factory()->create();
        $allowedLeague = $this->league('Liga Permitida', 'liga-permitida');
        $otherLeague = $this->league('Liga Privada', 'liga-privada');
        $this->membership($allowedLeague, $user, $this->leagueAdminRole);

        $this->actingAs($user)->post('/seleccionar-acceso', [
            'scope' => 'league',
            'league_id' => $otherLeague->id,
            'role_id' => $this->leagueAdminRole->id,
        ])->assertForbidden();

        $this->assertNull(session(LeagueContext::LEAGUE_KEY));
    }

    public function test_league_administrator_can_assign_another_role_by_exact_email(): void
    {
        $administrator = User::factory()->create();
        $target = User::factory()->create(['email' => 'arbitro@example.test']);
        $league = $this->league();
        $this->membership($league, $administrator, $this->leagueAdminRole);
        $refereeRole = Role::where('slug', 'referee')->firstOrFail();

        $this->actingAs($administrator)
            ->withSession([LeagueContext::LEAGUE_KEY => $league->id, LeagueContext::ROLE_KEY => $this->leagueAdminRole->id])
            ->post('/liga/miembros', [
                'email' => 'ARBITRO@example.test',
                'role_id' => $refereeRole->id,
                'reason' => 'Alta como árbitro',
            ])->assertSessionHasNoErrors();

        $this->assertDatabaseHas('league_memberships', [
            'league_id' => $league->id,
            'user_id' => $target->id,
            'role_id' => $refereeRole->id,
            'status' => 'active',
        ]);
        $this->assertDatabaseHas('audit_logs', [
            'league_id' => $league->id,
            'action' => 'league.membership.created',
        ]);
    }

    public function test_tampered_session_is_rejected_before_loading_league_data(): void
    {
        $user = User::factory()->create();
        $allowedLeague = $this->league('Liga Permitida', 'permitida');
        $otherLeague = $this->league('Liga Privada', 'privada');
        $this->membership($allowedLeague, $user, $this->leagueAdminRole);

        $this->actingAs($user)
            ->withSession([
                LeagueContext::LEAGUE_KEY => $otherLeague->id,
                LeagueContext::ROLE_KEY => $this->leagueAdminRole->id,
            ])
            ->get('/liga/miembros')
            ->assertRedirect('/seleccionar-acceso');
    }

    public function test_league_administrator_can_create_user_only_inside_active_league(): void
    {
        $administrator = User::factory()->create();
        $league = $this->league();
        $this->membership($league, $administrator, $this->leagueAdminRole);
        $playerRole = Role::where('slug', 'player')->firstOrFail();

        $this->actingAs($administrator)
            ->withSession([LeagueContext::LEAGUE_KEY => $league->id, LeagueContext::ROLE_KEY => $this->leagueAdminRole->id])
            ->post('/liga/miembros/crear-usuario', [
                'name' => 'Jugador Nuevo',
                'email' => 'jugador@example.test',
                'phone' => '',
                'password' => 'TemporaryPassword123',
                'password_confirmation' => 'TemporaryPassword123',
                'role_id' => $playerRole->id,
                'reason' => 'Registro inicial',
            ])->assertRedirect('/liga/miembros');

        $user = User::where('email', 'jugador@example.test')->firstOrFail();
        $this->assertTrue($user->force_password_change);
        $this->assertDatabaseHas('league_memberships', [
            'league_id' => $league->id,
            'user_id' => $user->id,
            'role_id' => $playerRole->id,
        ]);
    }

    public function test_direct_url_to_system_league_management_is_forbidden_for_league_admin(): void
    {
        $user = User::factory()->create();
        $league = $this->league();
        $this->membership($league, $user, $this->leagueAdminRole);

        $this->actingAs($user)
            ->withSession([LeagueContext::LEAGUE_KEY => $league->id, LeagueContext::ROLE_KEY => $this->leagueAdminRole->id])
            ->get('/administracion/ligas')
            ->assertForbidden();
    }

    public function test_membership_identifier_from_another_league_returns_not_found(): void
    {
        $administrator = User::factory()->create();
        $otherUser = User::factory()->create();
        $allowedLeague = $this->league('Liga Permitida', 'permitida');
        $otherLeague = $this->league('Liga Privada', 'privada');
        $this->membership($allowedLeague, $administrator, $this->leagueAdminRole);
        $foreignMembership = $this->membership($otherLeague, $otherUser, $this->leagueAdminRole);

        $this->actingAs($administrator)
            ->withSession([LeagueContext::LEAGUE_KEY => $allowedLeague->id, LeagueContext::ROLE_KEY => $this->leagueAdminRole->id])
            ->put("/liga/miembros/{$foreignMembership->id}", [
                'status' => 'suspended',
                'reason' => 'Intento fuera de liga',
            ])->assertNotFound();

        $this->assertDatabaseHas('league_memberships', ['id' => $foreignMembership->id, 'status' => 'active']);
    }

    public function test_last_active_league_administrator_cannot_be_suspended(): void
    {
        $user = User::factory()->create();
        $league = $this->league();
        $membership = $this->membership($league, $user, $this->leagueAdminRole);

        $this->actingAs($user)
            ->withSession([LeagueContext::LEAGUE_KEY => $league->id, LeagueContext::ROLE_KEY => $this->leagueAdminRole->id])
            ->put("/liga/miembros/{$membership->id}", ['status' => 'suspended', 'reason' => 'Prueba de seguridad'])
            ->assertSessionHasErrors('status');

        $this->assertDatabaseHas('league_memberships', ['id' => $membership->id, 'status' => 'active']);
    }

    private function league(string $name = 'Liga Taladzi', string $slug = 'liga-taladzi'): League
    {
        return League::create([
            'name' => $name,
            'slug' => $slug,
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
}
