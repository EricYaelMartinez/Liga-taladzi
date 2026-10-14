<?php

namespace Tests\Feature\League;

use App\Domain\Competition\Models\Category;
use App\Domain\Competition\Models\Competition;
use App\Domain\Competition\Models\Division;
use App\Domain\Competition\Models\Regulation;
use App\Domain\Competition\Models\Season;
use App\Domain\Competition\Models\Tournament;
use App\Domain\Discipline\Models\Sanction;
use App\Domain\Discipline\Services\DisciplineService;
use App\Domain\Identity\Models\Role;
use App\Domain\Identity\Models\User;
use App\Domain\League\Models\League;
use App\Domain\League\Models\LeagueMembership;
use App\Domain\League\Models\LeagueSetting;
use App\Domain\Player\Enums\PlayerStatus;
use App\Domain\Player\Models\Player;
use App\Domain\Player\Models\PlayerRegistration;
use App\Domain\Scheduling\Models\GameMatch;
use App\Domain\Scheduling\Models\MatchReport;
use App\Domain\Scheduling\Models\Matchday;
use App\Domain\Statistics\Services\CompetitionStatisticsService;
use App\Domain\Team\Models\Team;
use App\Domain\Team\Models\TeamParticipation;
use App\Domain\Team\Models\TeamRepresentative;
use App\Support\LeagueContext;
use Database\Seeders\IdentitySeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DisciplineManagementTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(IdentitySeeder::class);
    }

    public function test_red_card_creates_a_suspension_and_next_official_team_match_fulfills_it(): void
    {
        $scenario = $this->scenario();
        $first = $this->match($scenario, 1, $scenario['home'], $scenario['away']);
        $report = MatchReport::create(['match_id' => $first->id, 'folio' => 'CED-DISC-1', 'status' => 'approved', 'home_score' => 0, 'away_score' => 0, 'approved_at' => now(), 'reviewed_by' => $scenario['admin']->id]);
        $report->events()->create(['team_participation_id' => $scenario['home']->id, 'player_registration_id' => $scenario['registration']->id, 'type' => 'red_card', 'minute' => 70, 'period' => 'second_half']);

        app(DisciplineService::class)->syncReport($report);

        $sanction = Sanction::firstOrFail();
        $this->assertSame('automatic', $sanction->source);
        $this->assertSame('active', $sanction->status);
        $this->assertSame(1, $sanction->matches_total);
        $this->assertSame(PlayerStatus::Suspended, $scenario['player']->fresh()->status);

        $second = $this->match($scenario, 2, $scenario['home'], $scenario['third']);
        $secondReport = MatchReport::create(['match_id' => $second->id, 'folio' => 'CED-DISC-2', 'status' => 'approved', 'home_score' => 0, 'away_score' => 0, 'approved_at' => now()->addDay(), 'reviewed_by' => $scenario['admin']->id]);
        app(DisciplineService::class)->syncReport($secondReport);

        $this->assertSame('completed', $sanction->fresh()->status);
        $this->assertSame(1, $sanction->fresh()->matches_served);
        $this->assertSame(PlayerStatus::Active, $scenario['player']->fresh()->status);
    }

    public function test_team_representative_can_appeal_within_the_configured_deadline(): void
    {
        $scenario = $this->scenario();
        $sanction = $this->manualSanction($scenario, now()->addHours(2));
        $session = [LeagueContext::LEAGUE_KEY => $scenario['league']->id, LeagueContext::ROLE_KEY => $scenario['representativeRole']->id];

        $this->actingAs($scenario['representative'])->withSession($session)
            ->post("/liga/sanciones/{$sanction->id}/apelaciones", ['statement' => 'Solicitamos revisar la sanción porque existe evidencia suficiente.'])
            ->assertSessionHasNoErrors();

        $this->assertDatabaseHas('sanction_appeals', ['sanction_id' => $sanction->id, 'appellant_user_id' => $scenario['representative']->id, 'status' => 'submitted']);
    }

    public function test_appeal_is_rejected_after_the_deadline(): void
    {
        $scenario = $this->scenario();
        $sanction = $this->manualSanction($scenario, now()->subMinute());
        $session = [LeagueContext::LEAGUE_KEY => $scenario['league']->id, LeagueContext::ROLE_KEY => $scenario['representativeRole']->id];

        $this->actingAs($scenario['representative'])->withSession($session)
            ->post("/liga/sanciones/{$sanction->id}/apelaciones", ['statement' => 'Solicitamos revisar la sanción porque existe evidencia suficiente.'])
            ->assertSessionHasErrors('appeal');

        $this->assertDatabaseCount('sanction_appeals', 0);
    }

    public function test_active_points_deduction_changes_the_official_standings(): void
    {
        $scenario = $this->scenario();
        $match = $this->match($scenario, 1, $scenario['home'], $scenario['away']);
        MatchReport::create(['match_id' => $match->id, 'folio' => 'CED-PTS-1', 'status' => 'approved', 'home_score' => 2, 'away_score' => 0, 'approved_at' => now(), 'reviewed_by' => $scenario['admin']->id]);
        Sanction::create(['league_id' => $scenario['league']->id, 'season_id' => $scenario['season']->id, 'competition_id' => $scenario['competition']->id, 'team_id' => $scenario['home']->team_id, 'subject_type' => 'team', 'subject_name' => $scenario['home']->registered_name, 'kind' => 'points_deduction', 'source' => 'manual', 'reason' => 'Resolución administrativa', 'points_deducted' => 2, 'status' => 'active', 'issued_at' => now(), 'appeal_deadline_at' => now()->addHours(2), 'created_by' => $scenario['admin']->id]);

        $statistics = app(CompetitionStatisticsService::class)->calculate($scenario['competition']);

        $home = collect($statistics['standings'])->firstWhere('team_participation_id', $scenario['home']->id);
        $this->assertSame(1, $home['points']);
        $this->assertSame(2, $home['points_deducted']);
    }

    private function scenario(): array
    {
        $league = League::create(['name' => 'Liga Taladzi', 'slug' => 'liga-taladzi-'.uniqid(), 'primary_color' => '#125444', 'secondary_color' => '#d9a928', 'status' => 'active']);
        LeagueSetting::create(['league_id' => $league->id, 'match_periods' => 2, 'period_duration_minutes' => 45, 'halftime_minutes' => 15, 'schedule_buffer_minutes' => 15, 'appeal_deadline_hours' => 2, 'reactivation_window_days' => 21, 'currency' => 'MXN']);
        $season = Season::create(['league_id' => $league->id, 'name' => '2027', 'starts_on' => '2027-01-01', 'ends_on' => '2027-12-31', 'status' => 'active']);
        $tournament = Tournament::create(['season_id' => $season->id, 'name' => 'Liga', 'slug' => 'liga-'.uniqid(), 'status' => 'active']);
        $division = Division::create(['league_id' => $league->id, 'name' => 'Primera', 'slug' => 'primera-'.uniqid(), 'status' => 'active']);
        $category = Category::create(['league_id' => $league->id, 'name' => 'Libre', 'slug' => 'libre-'.uniqid(), 'status' => 'active']);
        $regulation = Regulation::create(['league_id' => $league->id, 'name' => 'General', 'version' => 1, 'status' => 'in_use', 'points_win' => 3, 'points_draw' => 1, 'points_loss' => 0, 'fair_play_yellow_points' => 1, 'fair_play_second_yellow_points' => 2, 'fair_play_red_points' => 3, 'league_settings_snapshot' => []]);
        foreach (['points', 'goal_difference', 'goals_for'] as $index => $criterion) $regulation->tiebreakers()->create(['criterion' => $criterion, 'priority' => $index + 1]);
        $competition = Competition::create(['tournament_id' => $tournament->id, 'division_id' => $division->id, 'category_id' => $category->id, 'regulation_id' => $regulation->id, 'name' => 'Primera Libre', 'format' => 'round_robin', 'regular_leg_count' => 1, 'knockout_leg_count' => 2, 'minimum_roster_size' => 1, 'maximum_roster_size' => 30, 'status' => 'active']);

        $admin = User::factory()->create();
        $adminRole = Role::where('slug', 'league_admin')->firstOrFail();
        LeagueMembership::create(['league_id' => $league->id, 'user_id' => $admin->id, 'role_id' => $adminRole->id, 'status' => 'active', 'started_at' => now()]);
        $representative = User::factory()->create();
        $representativeRole = Role::where('slug', 'team_representative')->firstOrFail();
        $membership = LeagueMembership::create(['league_id' => $league->id, 'user_id' => $representative->id, 'role_id' => $representativeRole->id, 'status' => 'active', 'started_at' => now()]);

        $home = $this->participation($league, $competition, $season, $division, 'Águilas');
        $away = $this->participation($league, $competition, $season, $division, 'Búhos');
        $third = $this->participation($league, $competition, $season, $division, 'Cóndores');
        TeamRepresentative::create([
            'team_id' => $home->team_id,
            'league_membership_id' => $membership->id,
            'user_id' => $representative->id,
            'photo_path' => 'private/representative-photo.png',
            'ine_path' => 'private/representative-ine.png',
            'status' => 'active',
            'started_at' => now(),
            'assigned_by' => $admin->id,
        ]);
        $player = Player::create(['league_id' => $league->id, 'full_name' => 'Jugador sancionado', 'birth_date' => '2000-01-01', 'gender' => 'unspecified', 'position' => 'forward', 'emergency_contact_name' => 'Contacto', 'emergency_contact_phone' => '9510000000', 'emergency_contact_relationship' => 'Familiar', 'photo_path' => 'players/test.png', 'photo_hash' => hash('sha256', uniqid()), 'status' => 'active']);
        $registration = PlayerRegistration::create(['player_id' => $player->id, 'team_id' => $home->team_id, 'team_participation_id' => $home->id, 'competition_id' => $competition->id, 'season_id' => $season->id, 'division_id' => $division->id, 'jersey_number' => 9, 'status' => 'active', 'requested_at' => now()]);

        return compact('league', 'season', 'competition', 'division', 'admin', 'adminRole', 'representative', 'representativeRole', 'home', 'away', 'third', 'player', 'registration');
    }

    private function participation(League $league, Competition $competition, Season $season, Division $division, string $name): TeamParticipation
    {
        $team = Team::create(['league_id' => $league->id, 'name' => $name, 'short_name' => $name, 'slug' => str($name.'-'.uniqid())->slug(), 'status' => 'active']);
        return TeamParticipation::create(['team_id' => $team->id, 'competition_id' => $competition->id, 'season_id' => $season->id, 'division_id' => $division->id, 'registered_name' => $name, 'status' => 'active', 'requested_at' => now()]);
    }

    private function match(array $scenario, int $number, TeamParticipation $home, TeamParticipation $away): GameMatch
    {
        $matchday = Matchday::create(['competition_id' => $scenario['competition']->id, 'number' => $number, 'name' => "Jornada {$number}", 'phase' => 'regular', 'starts_on' => "2027-02-0{$number}", 'ends_on' => "2027-02-0{$number}", 'status' => 'completed']);
        return GameMatch::create(['competition_id' => $scenario['competition']->id, 'matchday_id' => $matchday->id, 'home_team_participation_id' => $home->id, 'away_team_participation_id' => $away->id, 'scheduled_at' => now()->addDays($number), 'scheduled_end_at' => now()->addDays($number)->addMinutes(90), 'duration_minutes' => 90, 'status' => 'finished']);
    }

    private function manualSanction(array $scenario, $deadline): Sanction
    {
        return Sanction::create(['league_id' => $scenario['league']->id, 'season_id' => $scenario['season']->id, 'competition_id' => $scenario['competition']->id, 'team_id' => $scenario['home']->team_id, 'player_id' => $scenario['player']->id, 'subject_type' => 'player', 'subject_name' => $scenario['player']->full_name, 'kind' => 'suspension', 'source' => 'manual', 'reason' => 'Conducta antideportiva', 'matches_total' => 2, 'status' => 'active', 'issued_at' => now(), 'appeal_deadline_at' => $deadline, 'created_by' => $scenario['admin']->id]);
    }
}
