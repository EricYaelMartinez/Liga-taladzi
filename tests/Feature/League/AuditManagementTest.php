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
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class AuditManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_audit_filters_and_csv_remain_limited_to_active_league(): void
    {
        $this->seed(IdentitySeeder::class);
        $user = User::factory()->create();
        $role = Role::where('slug', 'league_admin')->firstOrFail();
        $league = $this->league('Liga auditada');
        $other = $this->league('Liga externa');
        LeagueMembership::create(['league_id' => $league->id, 'user_id' => $user->id, 'role_id' => $role->id, 'status' => 'active', 'started_at' => now()]);
        AuditLog::create(['actor_user_id' => $user->id, 'league_id' => $league->id, 'action' => 'team.updated', 'auditable_type' => League::class, 'auditable_id' => $league->id, 'ip_address' => '192.168.1.5', 'created_at' => now()]);
        AuditLog::create(['actor_user_id' => $user->id, 'league_id' => $other->id, 'action' => 'private.external', 'auditable_type' => League::class, 'auditable_id' => $other->id, 'created_at' => now()]);
        $session = [LeagueContext::LEAGUE_KEY => $league->id, LeagueContext::ROLE_KEY => $role->id];

        $this->actingAs($user)->withSession($session)->get('/liga/bitacora?action=team.updated&ip_address=192.168')
            ->assertOk()->assertInertia(fn (Assert $page) => $page->component('League/Audit/Index')->has('entries.data', 1)->where('entries.data.0.action', 'team.updated'));

        $csv = $this->actingAs($user)->withSession($session)->get('/liga/bitacora/exportar');
        $csv->assertOk()->assertDownload();
        $content = $csv->streamedContent();
        $this->assertStringContainsString('team.updated', $content);
        $this->assertStringNotContainsString('private.external', $content);
    }

    private function league(string $name): League
    {
        return League::create(['name' => $name, 'slug' => str($name.'-'.uniqid())->slug(), 'primary_color' => '#125444', 'secondary_color' => '#d9a928', 'status' => 'active']);
    }
}
