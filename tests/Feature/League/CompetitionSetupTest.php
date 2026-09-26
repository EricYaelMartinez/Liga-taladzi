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
use App\Domain\League\Models\LeagueSetting;
use App\Support\LeagueContext;
use Database\Seeders\IdentitySeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CompetitionSetupTest extends TestCase
{
    use RefreshDatabase;

    private Role $leagueAdminRole;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(IdentitySeeder::class);
        $this->leagueAdminRole = Role::where('slug', 'league_admin')->firstOrFail();
    }

    public function test_league_administrator_can_create_catalogs_season_and_tournament(): void
    {
        [$administrator, $league] = $this->leagueAdministrator();
        $session = $this->contextSession($league, $this->leagueAdminRole);

        $this->actingAs($administrator)->withSession($session)->post('/liga/divisiones', ['name' => 'Primera fuerza', 'sort_order' => 1])->assertSessionHasNoErrors();
        $this->actingAs($administrator)->withSession($session)->post('/liga/categorias', ['name' => 'Libre', 'gender' => 'mixed', 'minimum_age' => null, 'maximum_age' => null, 'requirements' => null])->assertSessionHasNoErrors();
        $this->actingAs($administrator)->withSession($session)->post('/liga/temporadas', $this->seasonPayload())->assertSessionHasNoErrors();
        $season = Season::firstOrFail();
        $this->actingAs($administrator)->withSession($session)->post('/liga/torneos', ['season_id' => $season->id, 'name' => 'Torneo de Liga'])->assertSessionHasNoErrors();

        $this->assertDatabaseHas('divisions', ['league_id' => $league->id, 'slug' => 'primera-fuerza']);
        $this->assertDatabaseHas('categories', ['league_id' => $league->id, 'slug' => 'libre']);
        $this->assertDatabaseHas('tournaments', ['season_id' => $season->id, 'slug' => 'torneo-de-liga']);
    }

    public function test_regulation_copies_operational_settings_and_preserves_tiebreak_order(): void
    {
        [$administrator, $league] = $this->leagueAdministrator(true);

        $this->actingAs($administrator)
            ->withSession($this->contextSession($league, $this->leagueAdminRole))
            ->post('/liga/reglamentos', $this->regulationPayload())
            ->assertSessionHasNoErrors();

        $regulation = Regulation::with('tiebreakers')->firstOrFail();
        $this->assertSame(40, $regulation->league_settings_snapshot['period_duration_minutes']);
        $this->assertSame(['points', 'goal_difference', 'goals_for', 'head_to_head', 'fair_play'], $regulation->tiebreakers->pluck('criterion')->all());
        $this->assertDatabaseHas('audit_logs', ['league_id' => $league->id, 'action' => 'competition.regulation.created']);
    }

    public function test_first_regulation_version_starts_at_one(): void
    {
        [$administrator, $league] = $this->leagueAdministrator(true);

        $this->actingAs($administrator)
            ->withSession($this->contextSession($league, $this->leagueAdminRole))
            ->post('/liga/reglamentos', $this->regulationPayload())
            ->assertSessionHasNoErrors();

        $this->assertDatabaseHas('regulations', [
            'league_id' => $league->id,
            'name' => 'Reglamento general',
            'version' => 1,
        ]);
    }

    public function test_published_regulation_is_immutable_and_new_version_can_be_created(): void
    {
        [$administrator, $league] = $this->leagueAdministrator(true);
        $session = $this->contextSession($league, $this->leagueAdminRole);
        $this->actingAs($administrator)->withSession($session)->post('/liga/reglamentos', $this->regulationPayload());
        $regulation = Regulation::firstOrFail();
        $this->actingAs($administrator)->withSession($session)->post("/liga/reglamentos/{$regulation->id}/publicar")->assertSessionHasNoErrors();

        $changed = $this->regulationPayload();
        $changed['points_win'] = 4;
        $this->actingAs($administrator)->withSession($session)->put("/liga/reglamentos/{$regulation->id}", $changed)->assertSessionHasErrors('name');
        $this->assertSame(3, $regulation->fresh()->points_win);

        $this->actingAs($administrator)->withSession($session)->post('/liga/reglamentos', $changed)->assertSessionHasNoErrors();
        $this->assertDatabaseHas('regulations', ['league_id' => $league->id, 'name' => 'Reglamento general', 'version' => 2, 'points_win' => 4]);
    }

    public function test_competition_requires_published_regulation_and_marks_it_in_use(): void
    {
        [$administrator, $league] = $this->leagueAdministrator(true);
        $session = $this->contextSession($league, $this->leagueAdminRole);
        [$tournament, $division, $category] = $this->structure($league, $administrator);
        $this->actingAs($administrator)->withSession($session)->post('/liga/reglamentos', $this->regulationPayload());
        $regulation = Regulation::firstOrFail();

        $payload = $this->competitionPayload($tournament, $division, $category, $regulation);
        $this->actingAs($administrator)->withSession($session)->post('/liga/competencias', $payload)->assertSessionHasErrors('regulation_id');
        $this->actingAs($administrator)->withSession($session)->post("/liga/reglamentos/{$regulation->id}/publicar");
        $this->actingAs($administrator)->withSession($session)->post('/liga/competencias', $payload)->assertSessionHasNoErrors();

        $this->assertSame('in_use', $regulation->fresh()->status->value);
        $this->assertDatabaseHas('competitions', ['tournament_id' => $tournament->id, 'division_id' => $division->id, 'category_id' => $category->id]);
    }

    public function test_optional_minimum_team_limit_does_not_block_a_valid_maximum(): void
    {
        [$administrator, $league] = $this->leagueAdministrator(true);
        $session = $this->contextSession($league, $this->leagueAdminRole);
        [$tournament, $division, $category] = $this->structure($league, $administrator);
        $this->actingAs($administrator)->withSession($session)->post('/liga/reglamentos', $this->regulationPayload());
        $regulation = Regulation::firstOrFail();
        $this->actingAs($administrator)->withSession($session)->post("/liga/reglamentos/{$regulation->id}/publicar");

        $payload = $this->competitionPayload($tournament, $division, $category, $regulation);
        $payload['minimum_teams'] = null;

        $this->actingAs($administrator)
            ->withSession($session)
            ->post('/liga/competencias', $payload)
            ->assertSessionHasNoErrors();

        $this->assertDatabaseHas('competitions', ['minimum_teams' => null, 'maximum_teams' => 20]);
    }

    public function test_season_follows_controlled_transitions_until_archived(): void
    {
        [$administrator, $league] = $this->leagueAdministrator();
        $session = $this->contextSession($league, $this->leagueAdminRole);
        $this->actingAs($administrator)->withSession($session)->post('/liga/temporadas', $this->seasonPayload());
        $season = Season::firstOrFail();

        foreach (['registration', 'active', 'finished', 'archived'] as $status) {
            $this->actingAs($administrator)->withSession($session)->put("/liga/temporadas/{$season->id}/estado", ['status' => $status])->assertSessionHasNoErrors();
            $season->refresh();
        }
        $this->assertSame('archived', $season->status->value);
    }

    public function test_player_cannot_manage_competitions_and_cross_league_ids_are_rejected(): void
    {
        $league = $this->league('Liga Taladzi');
        $player = User::factory()->create();
        $playerRole = Role::where('slug', 'player')->firstOrFail();
        $this->membership($league, $player, $playerRole);
        $this->actingAs($player)->withSession($this->contextSession($league, $playerRole))->get('/liga/competencias')->assertForbidden();

        [$administrator] = $this->leagueAdministrator(false, $league);
        $other = $this->league('Otra Liga');
        $foreignSeason = Season::create(['league_id' => $other->id, ...$this->seasonPayload(), 'created_by' => $administrator->id]);
        $this->actingAs($administrator)
            ->withSession($this->contextSession($league, $this->leagueAdminRole))
            ->post('/liga/torneos', ['season_id' => $foreignSeason->id, 'name' => 'Torneo ajeno'])
            ->assertNotFound();
    }

    private function leagueAdministrator(bool $settings = false, ?League $league = null): array
    {
        $administrator = User::factory()->create();
        $league ??= $this->league('Liga Taladzi');
        $this->membership($league, $administrator, $this->leagueAdminRole);
        if ($settings) LeagueSetting::create(['league_id' => $league->id, 'period_duration_minutes' => 40]);
        return [$administrator, $league];
    }

    private function league(string $name): League
    {
        return League::create(['name' => $name, 'slug' => str($name)->slug()->toString(), 'primary_color' => '#125444', 'secondary_color' => '#d9a928', 'status' => 'active']);
    }

    private function membership(League $league, User $user, Role $role): void
    {
        LeagueMembership::create(['league_id' => $league->id, 'user_id' => $user->id, 'role_id' => $role->id, 'status' => 'active', 'started_at' => now()]);
    }

    private function contextSession(League $league, Role $role): array
    {
        return [LeagueContext::LEAGUE_KEY => $league->id, LeagueContext::ROLE_KEY => $role->id];
    }

    private function seasonPayload(): array
    {
        return ['name' => 'Temporada 2027', 'starts_on' => '2027-01-10', 'ends_on' => '2027-12-10', 'registration_starts_at' => '2026-12-01 08:00:00', 'registration_ends_at' => '2027-01-05 20:00:00', 'status' => 'planning'];
    }

    private function regulationPayload(): array
    {
        return [
            'name' => 'Reglamento general', 'points_win' => 3, 'points_draw' => 1, 'points_loss' => 0,
            'walkover_home_goals' => 3, 'walkover_away_goals' => 0, 'fair_play_yellow_points' => 1,
            'fair_play_second_yellow_points' => 2, 'fair_play_red_points' => 3,
            'tiebreakers' => ['points', 'goal_difference', 'goals_for', 'head_to_head', 'fair_play'],
        ];
    }

    private function structure(League $league, User $user): array
    {
        $season = Season::create(['league_id' => $league->id, ...$this->seasonPayload(), 'created_by' => $user->id]);
        $tournament = Tournament::create(['season_id' => $season->id, 'name' => 'Liga', 'slug' => 'liga', 'status' => 'planning', 'created_by' => $user->id]);
        $division = Division::create(['league_id' => $league->id, 'name' => 'Primera fuerza', 'slug' => 'primera-fuerza', 'sort_order' => 1]);
        $category = Category::create(['league_id' => $league->id, 'name' => 'Libre', 'slug' => 'libre', 'gender' => 'mixed']);
        return [$tournament, $division, $category];
    }

    private function competitionPayload(Tournament $tournament, Division $division, Category $category, Regulation $regulation): array
    {
        return [
            'tournament_id' => $tournament->id, 'division_id' => $division->id, 'category_id' => $category->id,
            'regulation_id' => $regulation->id, 'name' => 'Primera Libre', 'format' => 'round_robin',
            'regular_leg_count' => 1, 'knockout_leg_count' => 2, 'minimum_teams' => null, 'maximum_teams' => 20,
            'minimum_roster_size' => 11, 'maximum_roster_size' => 30,
            'registration_starts_at' => '2026-12-01 08:00:00', 'registration_ends_at' => '2027-01-05 20:00:00',
        ];
    }
}
