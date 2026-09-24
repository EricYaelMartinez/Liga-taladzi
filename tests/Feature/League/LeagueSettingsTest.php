<?php

namespace Tests\Feature\League;

use App\Domain\Identity\Models\Role;
use App\Domain\Identity\Models\User;
use App\Domain\League\Models\League;
use App\Domain\League\Models\LeagueMembership;
use App\Support\LeagueContext;
use Database\Seeders\IdentitySeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class LeagueSettingsTest extends TestCase
{
    use RefreshDatabase;

    public function test_league_administrator_can_update_branding_and_logo(): void
    {
        Storage::fake('public');
        $this->seed(IdentitySeeder::class);
        $role = Role::where('slug', 'league_admin')->firstOrFail();
        $user = User::factory()->create();
        $league = League::create([
            'name' => 'Liga Inicial',
            'slug' => 'liga-inicial',
            'primary_color' => '#125444',
            'secondary_color' => '#d9a928',
            'status' => 'active',
        ]);
        LeagueMembership::create([
            'league_id' => $league->id,
            'user_id' => $user->id,
            'role_id' => $role->id,
            'status' => 'active',
            'started_at' => now(),
        ]);

        $this->actingAs($user)
            ->withSession([LeagueContext::LEAGUE_KEY => $league->id, LeagueContext::ROLE_KEY => $role->id])
            ->post('/liga/configuracion', [
                'name' => 'Liga Renovada',
                'primary_color' => '#123456',
                'secondary_color' => '#abcdef',
                'logo' => UploadedFile::fake()->createWithContent(
                    'escudo.png',
                    base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNk+A8AAQUBAScY42YAAAAASUVORK5CYII='),
                ),
                'reason' => 'Actualización institucional',
            ])->assertSessionHasNoErrors();

        $league->refresh();
        $this->assertSame('Liga Renovada', $league->name);
        Storage::disk('public')->assertExists($league->logo_path);
        $this->assertDatabaseHas('audit_logs', ['league_id' => $league->id, 'action' => 'league.settings.updated']);
    }
}
