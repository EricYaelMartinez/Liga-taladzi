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
use App\Domain\Player\Models\Player;
use App\Domain\Player\Models\PlayerRegistration;
use App\Domain\Referee\Models\Referee;
use App\Domain\Scheduling\Models\GameMatch;
use App\Domain\Scheduling\Models\MatchRefereeAssignment;
use App\Domain\Scheduling\Models\Matchday;
use App\Domain\Team\Models\Team;
use App\Domain\Team\Models\TeamParticipation;
use App\Domain\Team\Models\TeamRepresentative;
use App\Support\LeagueContext;
use Database\Seeders\IdentitySeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LineupManagementTest extends TestCase
{
    use RefreshDatabase;

    private Role $adminRole;
    private Role $representativeRole;
    private Role $refereeRole;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(IdentitySeeder::class);
        $this->adminRole = Role::where('slug', 'league_admin')->firstOrFail();
        $this->representativeRole = Role::where('slug', 'team_representative')->firstOrFail();
        $this->refereeRole = Role::where('slug', 'referee')->firstOrFail();
    }

    public function test_representative_saves_submits_and_cannot_reopen_own_lineup(): void
    {
        $scenario = $this->scenario();
        $session = $this->leagueSession($scenario['league'], $this->representativeRole);
        $payload = [
            'players' => $this->starterPayload(array_slice($scenario['homePlayers'], 0, 8)),
            'reason' => 'Alineación de la jornada',
        ];

        $this->actingAs($scenario['representative'])->withSession($session)
            ->put("/liga/partidos/{$scenario['match']->id}/alineaciones/{$scenario['home']->id}", $payload)
            ->assertSessionHasNoErrors();
        $this->assertDatabaseHas('match_lineups', ['match_id' => $scenario['match']->id, 'team_participation_id' => $scenario['home']->id, 'status' => 'draft']);
        $this->assertDatabaseHas('match_lineup_players', ['player_registration_id' => $scenario['homePlayer']->id, 'role' => 'starter', 'is_captain' => true]);

        $this->actingAs($scenario['representative'])->withSession($session)
            ->post("/liga/partidos/{$scenario['match']->id}/alineaciones/{$scenario['home']->id}/enviar", ['reason' => 'Envío definitivo'])
            ->assertSessionHasNoErrors();
        $this->assertDatabaseHas('match_lineups', ['match_id' => $scenario['match']->id, 'status' => 'submitted']);

        $this->actingAs($scenario['representative'])->withSession($session)
            ->put("/liga/partidos/{$scenario['match']->id}/alineaciones/{$scenario['home']->id}", $payload)
            ->assertSessionHasErrors('lineup');
        $this->assertDatabaseHas('audit_logs', ['action' => 'lineup.submitted']);
    }

    public function test_lineup_draft_allows_fewer_than_eight_but_submission_requires_eight_to_eleven_starters(): void
    {
        $scenario = $this->scenario();
        $session = $this->leagueSession($scenario['league'], $this->representativeRole);

        $this->actingAs($scenario['representative'])->withSession($session)
            ->put("/liga/partidos/{$scenario['match']->id}/alineaciones/{$scenario['home']->id}", [
                'players' => $this->starterPayload(array_slice($scenario['homePlayers'], 0, 7)),
                'reason' => 'Borrador incompleto',
            ])->assertSessionHasNoErrors();

        $this->actingAs($scenario['representative'])->withSession($session)
            ->post("/liga/partidos/{$scenario['match']->id}/alineaciones/{$scenario['home']->id}/enviar", [
                'reason' => 'Intento con siete titulares',
            ])->assertSessionHasErrors('lineup');
        $this->assertDatabaseHas('match_lineups', ['match_id' => $scenario['match']->id, 'status' => 'draft']);

        $this->actingAs($scenario['representative'])->withSession($session)
            ->put("/liga/partidos/{$scenario['match']->id}/alineaciones/{$scenario['home']->id}", [
                'players' => $this->starterPayload($scenario['homePlayers']),
                'reason' => 'Intento con doce titulares',
            ])->assertSessionHasErrors('players');
    }

    public function test_representative_cannot_manage_opponent_or_use_ineligible_player(): void
    {
        $scenario = $this->scenario();
        $session = $this->leagueSession($scenario['league'], $this->representativeRole);

        $this->actingAs($scenario['representative'])->withSession($session)
            ->put("/liga/partidos/{$scenario['match']->id}/alineaciones/{$scenario['away']->id}", [
                'players' => [['player_registration_id' => $scenario['awayPlayer']->id, 'role' => 'starter', 'is_captain' => false]],
                'reason' => 'Intento sobre rival',
            ])->assertForbidden();

        $this->actingAs($scenario['representative'])->withSession($session)
            ->put("/liga/partidos/{$scenario['match']->id}/alineaciones/{$scenario['home']->id}", [
                'players' => [['player_registration_id' => $scenario['awayPlayer']->id, 'role' => 'starter', 'is_captain' => false]],
                'reason' => 'Jugador incorrecto',
            ])->assertSessionHasErrors('players');
    }

    public function test_referee_can_view_assigned_match_but_cannot_edit_lineups(): void
    {
        $scenario = $this->scenario();
        $this->actingAs($scenario['administrator'])->withSession($this->leagueSession($scenario['league'], $this->adminRole))
            ->put("/liga/partidos/{$scenario['match']->id}/alineaciones/{$scenario['home']->id}", [
                'players' => $this->starterPayload(array_slice($scenario['homePlayers'], 0, 8)),
                'reason' => 'Captura administrativa',
            ])->assertSessionHasNoErrors();
        $this->actingAs($scenario['administrator'])->withSession($this->leagueSession($scenario['league'], $this->adminRole))
            ->post("/liga/partidos/{$scenario['match']->id}/alineaciones/{$scenario['home']->id}/enviar", ['reason' => 'Confirmación'])
            ->assertSessionHasNoErrors();

        $session = $this->leagueSession($scenario['league'], $this->refereeRole);
        $this->actingAs($scenario['refereeUser'])->withSession($session)->get('/liga/alineaciones')->assertOk();
        $this->actingAs($scenario['refereeUser'])->withSession($session)
            ->put("/liga/partidos/{$scenario['match']->id}/alineaciones/{$scenario['home']->id}", [
                'players' => [['player_registration_id' => $scenario['homePlayer']->id, 'role' => 'starter', 'is_captain' => false]],
                'reason' => 'Intento arbitral',
            ])->assertForbidden();
    }

    private function scenario(): array
    {
        $league = League::create(['name' => 'Liga Taladzi', 'slug' => 'liga-taladzi-'.uniqid(), 'primary_color' => '#125444', 'secondary_color' => '#d9a928', 'status' => 'active']);
        $administrator = User::factory()->create();
        $representative = User::factory()->create();
        $refereeUser = User::factory()->create();
        $this->membership($league, $administrator, $this->adminRole);
        $representativeMembership = $this->membership($league, $representative, $this->representativeRole);
        $this->membership($league, $refereeUser, $this->refereeRole);

        $season = Season::create(['league_id' => $league->id, 'name' => 'Temporada 2027', 'starts_on' => '2027-01-01', 'ends_on' => '2027-12-31', 'status' => 'active']);
        $tournament = Tournament::create(['season_id' => $season->id, 'name' => 'Liga', 'slug' => 'liga-'.uniqid(), 'status' => 'active']);
        $division = Division::create(['league_id' => $league->id, 'name' => 'Primera', 'slug' => 'primera-'.uniqid(), 'status' => 'active']);
        $category = Category::create(['league_id' => $league->id, 'name' => 'Libre', 'slug' => 'libre-'.uniqid(), 'status' => 'active']);
        $regulation = Regulation::create(['league_id' => $league->id, 'name' => 'Reglamento', 'version' => 1, 'status' => 'in_use', 'league_settings_snapshot' => []]);
        $competition = Competition::create(['tournament_id' => $tournament->id, 'division_id' => $division->id, 'category_id' => $category->id, 'regulation_id' => $regulation->id, 'name' => 'Primera Libre', 'format' => 'round_robin', 'regular_leg_count' => 1, 'knockout_leg_count' => 2, 'minimum_roster_size' => 1, 'maximum_roster_size' => 30, 'status' => 'active']);
        $home = $this->participation($league, $competition, $season, $division, 'Locales');
        $away = $this->participation($league, $competition, $season, $division, 'Visitantes');
        TeamRepresentative::create(['team_id' => $home->team_id, 'league_membership_id' => $representativeMembership->id, 'user_id' => $representative->id, 'photo_path' => 'private/rep.png', 'ine_path' => 'private/ine.png', 'status' => 'active', 'started_at' => now(), 'assigned_by' => $administrator->id]);

        $matchday = Matchday::create(['competition_id' => $competition->id, 'number' => 1, 'name' => 'Jornada 1', 'phase' => 'regular', 'starts_on' => '2027-01-04', 'ends_on' => '2027-01-04', 'status' => 'published', 'published_at' => now()]);
        $match = GameMatch::create(['competition_id' => $competition->id, 'matchday_id' => $matchday->id, 'home_team_participation_id' => $home->id, 'away_team_participation_id' => $away->id, 'scheduled_at' => '2027-01-04 10:00:00', 'scheduled_end_at' => '2027-01-04 11:45:00', 'duration_minutes' => 105, 'status' => 'scheduled']);
        $referee = Referee::create(['league_id' => $league->id, 'user_id' => $refereeUser->id, 'category_level' => 'Estatal', 'status' => 'active']);
        MatchRefereeAssignment::create(['match_id' => $match->id, 'referee_id' => $referee->id, 'role' => 'central', 'assigned_by' => $administrator->id]);

        $homePlayers = array_map(
            fn (int $number) => $this->registration($league, $home, "Jugador Local {$number}", $number),
            range(1, 12),
        );

        return compact('league', 'administrator', 'representative', 'refereeUser', 'home', 'away', 'match', 'homePlayers') + [
            'homePlayer' => $homePlayers[0],
            'awayPlayer' => $this->registration($league, $away, 'Jugador Visitante', 11),
        ];
    }

    private function starterPayload(array $registrations): array
    {
        return array_map(
            fn (PlayerRegistration $registration, int $index) => [
                'player_registration_id' => $registration->id,
                'role' => 'starter',
                'is_captain' => $index === 0,
            ],
            $registrations,
            array_keys($registrations),
        );
    }

    private function participation(League $league, Competition $competition, Season $season, Division $division, string $name): TeamParticipation
    {
        $team = Team::create(['league_id' => $league->id, 'name' => $name, 'short_name' => $name, 'slug' => str($name.'-'.uniqid())->slug(), 'status' => 'active']);
        return TeamParticipation::create(['team_id' => $team->id, 'competition_id' => $competition->id, 'season_id' => $season->id, 'division_id' => $division->id, 'registered_name' => $name, 'status' => 'active', 'requested_at' => now()]);
    }

    private function registration(League $league, TeamParticipation $participation, string $name, int $number): PlayerRegistration
    {
        $player = Player::create(['league_id' => $league->id, 'full_name' => $name, 'birth_date' => '2000-01-01', 'gender' => 'unspecified', 'position' => 'midfielder', 'emergency_contact_name' => 'Contacto', 'emergency_contact_phone' => '9510000000', 'emergency_contact_relationship' => 'Familiar', 'photo_path' => 'players/test.png', 'photo_hash' => hash('sha256', $name), 'status' => 'active']);
        return PlayerRegistration::create(['player_id' => $player->id, 'team_id' => $participation->team_id, 'team_participation_id' => $participation->id, 'competition_id' => $participation->competition_id, 'season_id' => $participation->season_id, 'division_id' => $participation->division_id, 'jersey_number' => $number, 'status' => 'active', 'requested_at' => now()]);
    }

    private function membership(League $league, User $user, Role $role): LeagueMembership
    {
        return LeagueMembership::create(['league_id' => $league->id, 'user_id' => $user->id, 'role_id' => $role->id, 'status' => 'active', 'started_at' => now()]);
    }

    private function leagueSession(League $league, Role $role): array
    {
        return [LeagueContext::LEAGUE_KEY => $league->id, LeagueContext::ROLE_KEY => $role->id];
    }
}
