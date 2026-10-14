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
use App\Domain\Scheduling\Models\GameMatch;
use App\Domain\Scheduling\Models\MatchReport;
use App\Domain\Scheduling\Models\Matchday;
use App\Domain\Team\Models\Team;
use App\Domain\Team\Models\TeamParticipation;
use App\Support\LeagueContext;
use Database\Seeders\IdentitySeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class StatisticsTest extends TestCase
{
    use RefreshDatabase;

    public function test_only_approved_reports_feed_results_standings_scorers_and_discipline(): void
    {
        $scenario = $this->scenario();
        $session = [LeagueContext::LEAGUE_KEY => $scenario['league']->id, LeagueContext::ROLE_KEY => $scenario['role']->id];

        $this->actingAs($scenario['user'])->withSession($session)
            ->get("/liga/resultados?competition_id={$scenario['competition']->id}")
            ->assertInertia(fn (Assert $page) => $page
                ->component('League/Statistics/Index')
                ->where('selectedCompetitionId', $scenario['competition']->id)
                ->where('statistics.summary.official_matches', 2)
                ->where('statistics.summary.total_goals', 3)
                ->where('statistics.standings.0.team', 'Águilas')
                ->where('statistics.standings.0.points', 3)
                ->where('statistics.standings.0.goal_difference', 2)
                ->where('statistics.standings.1.team', 'Búhos')
                ->where('statistics.scorers.0.player', 'Goleador Águilas')
                ->where('statistics.scorers.0.goals', 2)
                ->where('statistics.discipline.0.player', 'Defensa Cóndores')
                ->where('statistics.discipline.0.red_cards', 1));
    }

    public function test_competitions_from_another_league_cannot_be_queried(): void
    {
        $scenario = $this->scenario();
        $other = $this->structure($this->league('Liga externa'))['competition'];
        $session = [LeagueContext::LEAGUE_KEY => $scenario['league']->id, LeagueContext::ROLE_KEY => $scenario['role']->id];

        $this->actingAs($scenario['user'])->withSession($session)
            ->get("/liga/resultados?competition_id={$other->id}")
            ->assertNotFound();
    }

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(IdentitySeeder::class);
    }

    private function scenario(): array
    {
        $league = $this->league('Liga Taladzi');
        $structure = $this->structure($league);
        $competition = $structure['competition'];
        $role = Role::where('slug', 'player')->firstOrFail();
        $user = User::factory()->create();
        LeagueMembership::create(['league_id' => $league->id, 'user_id' => $user->id, 'role_id' => $role->id, 'status' => 'active', 'started_at' => now()]);

        $eagles = $this->participation($competition, $structure, 'Águilas');
        $owls = $this->participation($competition, $structure, 'Búhos');
        $condors = $this->participation($competition, $structure, 'Cóndores');
        $eaglesPlayer = $this->registration($eagles, $structure, 'Goleador Águilas', 9);
        $owlsPlayer = $this->registration($owls, $structure, 'Volante Búhos', 10);
        $condorsPlayer = $this->registration($condors, $structure, 'Defensa Cóndores', 4);

        $first = $this->match($competition, 1, $eagles, $owls);
        $firstReport = MatchReport::create(['match_id' => $first->id, 'folio' => 'CED-TEST-1', 'status' => 'approved', 'home_score' => 2, 'away_score' => 0, 'approved_at' => now()]);
        foreach ([15, 70] as $minute) $firstReport->events()->create(['team_participation_id' => $eagles->id, 'player_registration_id' => $eaglesPlayer->id, 'type' => 'goal', 'minute' => $minute, 'period' => 'match']);
        $firstReport->events()->create(['team_participation_id' => $owls->id, 'player_registration_id' => $owlsPlayer->id, 'type' => 'yellow_card', 'minute' => 50, 'period' => 'match']);

        $second = $this->match($competition, 2, $owls, $condors);
        $secondReport = MatchReport::create(['match_id' => $second->id, 'folio' => 'CED-TEST-2', 'status' => 'approved', 'home_score' => 1, 'away_score' => 0, 'approved_at' => now()]);
        $secondReport->events()->create(['team_participation_id' => $owls->id, 'player_registration_id' => $owlsPlayer->id, 'type' => 'goal', 'minute' => 30, 'period' => 'match']);
        $secondReport->events()->create(['team_participation_id' => $condors->id, 'player_registration_id' => $condorsPlayer->id, 'type' => 'red_card', 'minute' => 75, 'period' => 'match']);

        $unapproved = $this->match($competition, 3, $condors, $eagles);
        MatchReport::create(['match_id' => $unapproved->id, 'status' => 'under_review', 'home_score' => 5, 'away_score' => 0]);

        return compact('league', 'competition', 'role', 'user');
    }

    private function structure(League $league): array
    {
        $season = Season::create(['league_id' => $league->id, 'name' => 'Temporada '.uniqid(), 'starts_on' => '2027-01-01', 'ends_on' => '2027-12-31', 'status' => 'active']);
        $tournament = Tournament::create(['season_id' => $season->id, 'name' => 'Liga', 'slug' => 'liga-'.uniqid(), 'status' => 'active']);
        $division = Division::create(['league_id' => $league->id, 'name' => 'Primera '.uniqid(), 'slug' => 'primera-'.uniqid(), 'status' => 'active']);
        $category = Category::create(['league_id' => $league->id, 'name' => 'Libre '.uniqid(), 'slug' => 'libre-'.uniqid(), 'status' => 'active']);
        $regulation = Regulation::create(['league_id' => $league->id, 'name' => 'General '.uniqid(), 'version' => 1, 'status' => 'in_use', 'points_win' => 3, 'points_draw' => 1, 'points_loss' => 0, 'fair_play_yellow_points' => 1, 'fair_play_second_yellow_points' => 2, 'fair_play_red_points' => 3, 'league_settings_snapshot' => []]);
        foreach (['points', 'goal_difference', 'goals_for', 'head_to_head', 'fair_play'] as $index => $criterion) $regulation->tiebreakers()->create(['criterion' => $criterion, 'priority' => $index + 1]);
        $competition = Competition::create(['tournament_id' => $tournament->id, 'division_id' => $division->id, 'category_id' => $category->id, 'regulation_id' => $regulation->id, 'name' => 'Primera Libre', 'format' => 'round_robin', 'regular_leg_count' => 1, 'knockout_leg_count' => 2, 'minimum_roster_size' => 1, 'maximum_roster_size' => 30, 'status' => 'active']);
        return compact('season', 'tournament', 'division', 'category', 'regulation', 'competition');
    }

    private function league(string $name): League
    {
        return League::create(['name' => $name, 'slug' => str($name.'-'.uniqid())->slug(), 'primary_color' => '#125444', 'secondary_color' => '#d9a928', 'status' => 'active']);
    }

    private function participation(Competition $competition, array $structure, string $name): TeamParticipation
    {
        $team = Team::create(['league_id' => $structure['season']->league_id, 'name' => $name, 'short_name' => $name, 'slug' => str($name.'-'.uniqid())->slug(), 'status' => 'active']);
        return TeamParticipation::create(['team_id' => $team->id, 'competition_id' => $competition->id, 'season_id' => $structure['season']->id, 'division_id' => $structure['division']->id, 'registered_name' => $name, 'status' => 'active', 'requested_at' => now()]);
    }

    private function registration(TeamParticipation $participation, array $structure, string $name, int $number): PlayerRegistration
    {
        $player = Player::create(['league_id' => $structure['season']->league_id, 'full_name' => $name, 'birth_date' => '2000-01-01', 'gender' => 'unspecified', 'position' => 'forward', 'emergency_contact_name' => 'Contacto', 'emergency_contact_phone' => '9510000000', 'emergency_contact_relationship' => 'Familiar', 'photo_path' => 'players/test.png', 'photo_hash' => hash('sha256', $name), 'status' => 'active']);
        return PlayerRegistration::create(['player_id' => $player->id, 'team_id' => $participation->team_id, 'team_participation_id' => $participation->id, 'competition_id' => $participation->competition_id, 'season_id' => $participation->season_id, 'division_id' => $participation->division_id, 'jersey_number' => $number, 'status' => 'active', 'requested_at' => now()]);
    }

    private function match(Competition $competition, int $number, TeamParticipation $home, TeamParticipation $away): GameMatch
    {
        $matchday = Matchday::create(['competition_id' => $competition->id, 'number' => $number, 'name' => "Jornada {$number}", 'phase' => 'regular', 'starts_on' => "2027-01-0{$number}", 'ends_on' => "2027-01-0{$number}", 'status' => 'completed']);
        return GameMatch::create(['competition_id' => $competition->id, 'matchday_id' => $matchday->id, 'home_team_participation_id' => $home->id, 'away_team_participation_id' => $away->id, 'scheduled_at' => now()->subDays(4 - $number), 'scheduled_end_at' => now()->subDays(4 - $number)->addMinutes(90), 'duration_minutes' => 90, 'status' => 'finished']);
    }
}
