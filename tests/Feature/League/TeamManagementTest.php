<?php

namespace Tests\Feature\League;

use App\Domain\Competition\Models\Category;
use App\Domain\Competition\Models\Competition;
use App\Domain\Competition\Models\Division;
use App\Domain\Competition\Models\Regulation;
use App\Domain\Competition\Models\Season;
use App\Domain\Competition\Models\Tournament;
use App\Domain\Identity\Models\Role;
use App\Domain\Identity\Models\User;
use App\Domain\League\Models\League;
use App\Domain\League\Models\LeagueMembership;
use App\Domain\Team\Models\Team;
use App\Domain\Team\Models\TeamChangeRequest;
use App\Domain\Team\Models\TeamParticipation;
use App\Domain\Team\Models\TeamRepresentative;
use App\Support\LeagueContext;
use Database\Seeders\IdentitySeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class TeamManagementTest extends TestCase
{
    use RefreshDatabase;

    private Role $adminRole;
    private Role $representativeRole;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('public');
        Storage::fake('local');
        $this->seed(IdentitySeeder::class);
        $this->adminRole = Role::where('slug', 'league_admin')->firstOrFail();
        $this->representativeRole = Role::where('slug', 'team_representative')->firstOrFail();
    }

    public function test_administrator_registers_team_representative_and_pending_participation(): void
    {
        [$administrator, $league] = $this->administrator();
        [$representative, $membership] = $this->representative($league);
        $competition = $this->competition($league, $administrator);

        $this->actingAs($administrator)->withSession($this->leagueSession($league, $this->adminRole))
            ->post('/liga/equipos', $this->teamPayload($competition, $membership, 'Deportivo Taladzi'))
            ->assertSessionHasNoErrors();

        $team = Team::firstOrFail();
        $this->assertSame('pending', $team->status->value);
        $this->assertDatabaseHas('team_representatives', ['team_id' => $team->id, 'user_id' => $representative->id, 'status' => 'active']);
        $this->assertDatabaseHas('team_participations', ['team_id' => $team->id, 'competition_id' => $competition->id, 'status' => 'pending']);
        $this->assertDatabaseHas('team_name_histories', ['team_id' => $team->id, 'name' => 'Deportivo Taladzi']);
    }

    public function test_duplicate_team_name_is_rejected_case_insensitively(): void
    {
        [$administrator, $league] = $this->administrator();
        [, $firstMembership] = $this->representative($league, 'Propietario Uno');
        [, $secondMembership] = $this->representative($league, 'Propietario Dos');
        $competition = $this->competition($league, $administrator);
        $session = $this->leagueSession($league, $this->adminRole);

        $this->actingAs($administrator)->withSession($session)->post('/liga/equipos', $this->teamPayload($competition, $firstMembership, 'Deportivo Taladzi'));
        $this->actingAs($administrator)->withSession($session)
            ->post('/liga/equipos', $this->teamPayload($competition, $secondMembership, 'DEPORTIVO TALADZI'))
            ->assertSessionHasErrors('name');

        $this->assertSame(1, Team::count());
    }

    public function test_representative_cannot_manage_two_teams(): void
    {
        [$administrator, $league] = $this->administrator();
        [, $membership] = $this->representative($league);
        $competition = $this->competition($league, $administrator);
        $session = $this->leagueSession($league, $this->adminRole);
        $this->actingAs($administrator)->withSession($session)->post('/liga/equipos', $this->teamPayload($competition, $membership, 'Equipo Uno'));

        $this->actingAs($administrator)->withSession($session)
            ->post('/liga/equipos', $this->teamPayload($competition, $membership, 'Equipo Dos'))
            ->assertSessionHasErrors('league_membership_id');

        $this->assertSame(1, TeamRepresentative::where('status', 'active')->count());
    }

    public function test_administrator_can_approve_suspend_and_reactivate_participation(): void
    {
        [$administrator, $league] = $this->administrator();
        [, $membership] = $this->representative($league);
        $competition = $this->competition($league, $administrator);
        $session = $this->leagueSession($league, $this->adminRole);
        $this->actingAs($administrator)->withSession($session)->post('/liga/equipos', $this->teamPayload($competition, $membership, 'Equipo Uno'));
        $participation = TeamParticipation::firstOrFail();

        foreach ([['approve', 'active'], ['suspend', 'suspended'], ['reactivate', 'active']] as [$action, $status]) {
            $this->actingAs($administrator)->withSession($session)
                ->put("/liga/participaciones/{$participation->id}/estado", ['action' => $action, 'reason' => "Cambio a {$status}"])
                ->assertSessionHasNoErrors();
            $this->assertSame($status, $participation->fresh()->status->value);
        }
        $this->assertSame('active', $participation->team->fresh()->status->value);
    }

    public function test_team_cannot_register_twice_in_the_same_season(): void
    {
        [$administrator, $league] = $this->administrator();
        [, $membership] = $this->representative($league);
        $competition = $this->competition($league, $administrator);
        $session = $this->leagueSession($league, $this->adminRole);
        $this->actingAs($administrator)->withSession($session)->post('/liga/equipos', $this->teamPayload($competition, $membership, 'Equipo Uno'));
        $team = Team::firstOrFail();

        $this->actingAs($administrator)->withSession($session)
            ->post("/liga/equipos/{$team->id}/participaciones", [
                'competition_id' => $competition->id,
                'reason' => 'Intento duplicado',
            ])->assertSessionHasErrors('competition_id');

        $this->assertSame(1, $team->participations()->count());
    }

    public function test_representative_only_sees_owned_team_and_can_request_changes(): void
    {
        [$administrator, $league] = $this->administrator();
        [$representative, $membership] = $this->representative($league);
        $competition = $this->competition($league, $administrator);
        $this->actingAs($administrator)->withSession($this->leagueSession($league, $this->adminRole))->post('/liga/equipos', $this->teamPayload($competition, $membership, 'Equipo Propio'));
        $team = Team::firstOrFail();

        $this->actingAs($representative)->withSession($this->leagueSession($league, $this->representativeRole))
            ->get('/liga/equipos')->assertOk();
        $payload = $this->teamData('Nuevo Nombre');
        $payload['request_reason'] = 'Actualización solicitada por el propietario';
        $this->actingAs($representative)->withSession($this->leagueSession($league, $this->representativeRole))
            ->post("/liga/equipos/{$team->id}/solicitudes-cambio", $payload)
            ->assertSessionHasNoErrors();

        $this->assertSame('Equipo Propio', $team->fresh()->name);
        $this->assertDatabaseHas('team_change_requests', ['team_id' => $team->id, 'requested_by' => $representative->id, 'status' => 'pending']);
    }

    public function test_administrator_approves_change_and_preserves_name_history(): void
    {
        [$administrator, $league] = $this->administrator();
        [$representative, $membership] = $this->representative($league);
        $competition = $this->competition($league, $administrator);
        $this->actingAs($administrator)->withSession($this->leagueSession($league, $this->adminRole))->post('/liga/equipos', $this->teamPayload($competition, $membership, 'Nombre Original'));
        $team = Team::firstOrFail();
        $change = TeamChangeRequest::create([
            'team_id' => $team->id, 'requested_by' => $representative->id,
            'changes' => [...$this->teamData('Nombre Nuevo'), 'slug' => 'nombre-nuevo'],
            'status' => 'pending', 'request_reason' => 'Cambio de identidad',
        ]);

        $this->actingAs($administrator)->withSession($this->leagueSession($league, $this->adminRole))
            ->put("/liga/solicitudes-cambio/{$change->id}", ['decision' => 'approve', 'reason' => 'Aprobado por la liga'])
            ->assertSessionHasNoErrors();

        $this->assertSame('Nombre Nuevo', $team->fresh()->name);
        $this->assertDatabaseHas('team_name_histories', ['team_id' => $team->id, 'name' => 'Nombre Original']);
        $this->assertDatabaseHas('team_name_histories', ['team_id' => $team->id, 'name' => 'Nombre Nuevo']);
    }

    public function test_player_cannot_open_team_management(): void
    {
        $league = $this->league('Liga Taladzi');
        $player = User::factory()->create();
        $playerRole = Role::where('slug', 'player')->firstOrFail();
        $this->membership($league, $player, $playerRole);

        $this->actingAs($player)->withSession($this->leagueSession($league, $playerRole))
            ->get('/liga/equipos')->assertForbidden();
    }

    public function test_team_from_another_league_cannot_be_modified(): void
    {
        [$administrator, $league] = $this->administrator();
        $otherLeague = $this->league('Otra Liga');
        $foreignTeam = Team::create(['league_id' => $otherLeague->id, ...$this->teamData('Equipo Ajeno'), 'slug' => 'equipo-ajeno', 'status' => 'active']);
        $payload = [...$this->teamData('Nombre Alterado'), 'reason' => 'Intento cruzado'];

        $this->actingAs($administrator)->withSession($this->leagueSession($league, $this->adminRole))
            ->post("/liga/equipos/{$foreignTeam->id}/actualizar", $payload)
            ->assertNotFound();
    }

    private function administrator(): array
    {
        $user = User::factory()->create();
        $league = $this->league('Liga Taladzi');
        $this->membership($league, $user, $this->adminRole);
        return [$user, $league];
    }

    private function representative(League $league, string $name = 'Propietario'): array
    {
        $user = User::factory()->create(['name' => $name]);
        return [$user, $this->membership($league, $user, $this->representativeRole)];
    }

    private function league(string $name): League
    {
        return League::create(['name' => $name, 'slug' => str($name)->slug()->toString(), 'primary_color' => '#125444', 'secondary_color' => '#d9a928', 'status' => 'active']);
    }

    private function membership(League $league, User $user, Role $role): LeagueMembership
    {
        return LeagueMembership::create(['league_id' => $league->id, 'user_id' => $user->id, 'role_id' => $role->id, 'status' => 'active', 'started_at' => now()]);
    }

    private function leagueSession(League $league, Role $role): array
    {
        return [LeagueContext::LEAGUE_KEY => $league->id, LeagueContext::ROLE_KEY => $role->id];
    }

    private function competition(League $league, User $user): Competition
    {
        $season = Season::create(['league_id' => $league->id, 'name' => 'Temporada 2027', 'starts_on' => '2027-01-01', 'ends_on' => '2027-12-31', 'status' => 'planning', 'created_by' => $user->id]);
        $tournament = Tournament::create(['season_id' => $season->id, 'name' => 'Liga', 'slug' => 'liga', 'status' => 'planning', 'created_by' => $user->id]);
        $division = Division::create(['league_id' => $league->id, 'name' => 'Primera fuerza', 'slug' => 'primera-fuerza', 'sort_order' => 1]);
        $category = Category::create(['league_id' => $league->id, 'name' => 'Libre', 'slug' => 'libre', 'gender' => 'mixed']);
        $regulation = Regulation::create(['league_id' => $league->id, 'name' => 'Reglamento', 'version' => 1, 'status' => 'published', 'league_settings_snapshot' => [], 'created_by' => $user->id]);
        return Competition::create([
            'tournament_id' => $tournament->id, 'division_id' => $division->id, 'category_id' => $category->id,
            'regulation_id' => $regulation->id, 'name' => 'Primera Libre', 'format' => 'round_robin',
            'regular_leg_count' => 1, 'knockout_leg_count' => 2, 'minimum_roster_size' => 11,
            'maximum_roster_size' => 30, 'status' => 'planning', 'created_by' => $user->id,
        ]);
    }

    private function teamPayload(Competition $competition, LeagueMembership $membership, string $name): array
    {
        return [
            ...$this->teamData($name), 'competition_id' => $competition->id,
            'league_membership_id' => $membership->id,
            'representative_photo' => $this->fakeImage('representante.png'),
            'representative_ine' => $this->fakeImage('ine.png'),
            'crest' => $this->fakeImage('escudo.png'),
            'reason' => 'Registro para la temporada',
        ];
    }

    private function teamData(string $name): array
    {
        return [
            'name' => $name, 'short_name' => mb_substr($name, 0, 12),
            'primary_color' => '#125444', 'secondary_color' => '#ffffff',
            'phone' => '9510000000', 'email' => 'equipo@example.com',
            'founded_on' => '2020-01-01', 'description' => 'Equipo local',
        ];
    }

    private function fakeImage(string $name): UploadedFile
    {
        $png = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNk+A8AAQUBAScY42YAAAAASUVORK5CYII=', true);
        return UploadedFile::fake()->createWithContent($name, $png);
    }
}
