<?php

namespace Tests\Feature\League;

use App\Domain\Audit\Models\AuditLog;
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
use App\Domain\Reporting\Services\LeagueReportService;
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

class ExportManagementTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(IdentitySeeder::class);
    }

    public function test_league_administrator_opens_the_report_center_and_downloads_pdf(): void
    {
        $scenario = $this->scenario();

        $this->actingAs($scenario['user'])->withSession($scenario['session'])
            ->get('/liga/reportes')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('League/Exports/Index')
                ->has('reportTypes', 12)
                ->where('reportTypes.0.value', 'teams')
                ->has('options.seasons', 1)
                ->has('options.competitions', 1));

        $this->actingAs($scenario['user'])->withSession($scenario['session'])
            ->get('/liga/reportes/exportar?type=teams&format=pdf')
            ->assertOk()
            ->assertHeader('content-type', 'application/pdf')
            ->assertDownload();

        $this->assertTrue(AuditLog::where('league_id', $scenario['league']->id)->where('action', 'report.exported')->exists());
    }

    public function test_excel_export_uses_the_native_xlsx_format(): void
    {
        $scenario = $this->scenario();

        $this->actingAs($scenario['user'])->withSession($scenario['session'])
            ->get('/liga/reportes/exportar?type=schedule&format=xlsx')
            ->assertOk()
            ->assertHeader('content-type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet')
            ->assertDownload();
    }

    public function test_results_report_only_uses_approved_match_reports(): void
    {
        $scenario = $this->scenario();
        $report = app(LeagueReportService::class)->generate($scenario['league'], 'results', []);

        $this->assertSame(1, $report['total']);
        $this->assertSame('CED-OFICIAL-1', $report['rows'][0][0]);
        $this->assertSame('2 - 1', $report['rows'][0][4]);
    }

    public function test_every_planned_report_can_be_generated_with_valid_filters(): void
    {
        $scenario = $this->scenario();
        $service = app(LeagueReportService::class);

        foreach (LeagueReportService::TYPES as $type) {
            $filters = in_array($type, ['standings', 'scorers', 'cards'], true)
                ? ['competition_id' => $scenario['competition']->id]
                : [];
            $report = $service->generate($scenario['league'], $type, $filters);
            $this->assertNotEmpty($report['title']);
            $this->assertNotEmpty($report['columns']);
            $this->assertIsArray($report['rows']);
        }
    }

    public function test_non_administrators_cannot_access_administrative_exports(): void
    {
        $scenario = $this->scenario('team_representative');

        $this->actingAs($scenario['user'])->withSession($scenario['session'])
            ->get('/liga/reportes')
            ->assertForbidden();
        $this->actingAs($scenario['user'])->withSession($scenario['session'])
            ->get('/liga/reportes/exportar?type=rosters&format=xlsx')
            ->assertForbidden();
    }

    public function test_a_competition_from_another_league_cannot_be_exported(): void
    {
        $scenario = $this->scenario();
        $other = $this->structure($this->league('Liga externa'))['competition'];

        $this->actingAs($scenario['user'])->withSession($scenario['session'])
            ->get("/liga/reportes/exportar?type=standings&format=pdf&competition_id={$other->id}")
            ->assertNotFound();
    }

    private function scenario(string $roleSlug = 'league_admin'): array
    {
        $league = $this->league('Liga de reportes');
        $structure = $this->structure($league);
        $competition = $structure['competition'];
        $role = Role::where('slug', $roleSlug)->firstOrFail();
        $user = User::factory()->create();
        LeagueMembership::create(['league_id' => $league->id, 'user_id' => $user->id, 'role_id' => $role->id, 'status' => 'active', 'started_at' => now()]);
        $home = $this->participation($league, $structure, 'Águilas');
        $away = $this->participation($league, $structure, 'Búhos');

        $official = $this->game($competition, 1, $home, $away);
        MatchReport::create(['match_id' => $official->id, 'folio' => 'CED-OFICIAL-1', 'status' => 'approved', 'home_score' => 2, 'away_score' => 1, 'approved_at' => now()]);
        $pending = $this->game($competition, 2, $away, $home);
        MatchReport::create(['match_id' => $pending->id, 'status' => 'under_review', 'home_score' => 8, 'away_score' => 0]);

        $session = [LeagueContext::LEAGUE_KEY => $league->id, LeagueContext::ROLE_KEY => $role->id];
        return compact('league', 'competition', 'user', 'session');
    }

    private function structure(League $league): array
    {
        $suffix = uniqid();
        $season = Season::create(['league_id' => $league->id, 'name' => 'Temporada '.$suffix, 'starts_on' => '2027-01-01', 'ends_on' => '2027-12-31', 'status' => 'active']);
        $tournament = Tournament::create(['season_id' => $season->id, 'name' => 'Torneo', 'slug' => 'torneo-'.$suffix, 'status' => 'active']);
        $division = Division::create(['league_id' => $league->id, 'name' => 'Primera '.$suffix, 'slug' => 'primera-'.$suffix, 'status' => 'active']);
        $category = Category::create(['league_id' => $league->id, 'name' => 'Libre '.$suffix, 'slug' => 'libre-'.$suffix, 'status' => 'active']);
        $regulation = Regulation::create(['league_id' => $league->id, 'name' => 'General '.$suffix, 'version' => 1, 'status' => 'in_use', 'points_win' => 3, 'points_draw' => 1, 'points_loss' => 0, 'fair_play_yellow_points' => 1, 'fair_play_second_yellow_points' => 2, 'fair_play_red_points' => 3, 'league_settings_snapshot' => []]);
        foreach (['points', 'goal_difference', 'goals_for', 'head_to_head', 'fair_play'] as $index => $criterion) $regulation->tiebreakers()->create(['criterion' => $criterion, 'priority' => $index + 1]);
        $competition = Competition::create(['tournament_id' => $tournament->id, 'division_id' => $division->id, 'category_id' => $category->id, 'regulation_id' => $regulation->id, 'name' => 'Primera Libre', 'format' => 'round_robin', 'regular_leg_count' => 1, 'knockout_leg_count' => 2, 'minimum_roster_size' => 8, 'maximum_roster_size' => 30, 'status' => 'active']);
        return compact('season', 'division', 'competition');
    }

    private function participation(League $league, array $structure, string $name): TeamParticipation
    {
        $team = Team::create(['league_id' => $league->id, 'name' => $name, 'short_name' => $name, 'slug' => str($name.'-'.uniqid())->slug(), 'status' => 'active']);
        return TeamParticipation::create(['team_id' => $team->id, 'competition_id' => $structure['competition']->id, 'season_id' => $structure['season']->id, 'division_id' => $structure['division']->id, 'registered_name' => $name, 'status' => 'active', 'requested_at' => now()]);
    }

    private function game(Competition $competition, int $number, TeamParticipation $home, TeamParticipation $away): GameMatch
    {
        $matchday = Matchday::create(['competition_id' => $competition->id, 'number' => $number, 'name' => "Jornada {$number}", 'phase' => 'regular', 'starts_on' => "2027-01-0{$number}", 'ends_on' => "2027-01-0{$number}", 'status' => 'completed']);
        return GameMatch::create(['competition_id' => $competition->id, 'matchday_id' => $matchday->id, 'home_team_participation_id' => $home->id, 'away_team_participation_id' => $away->id, 'scheduled_at' => now()->subDays(3 - $number), 'scheduled_end_at' => now()->subDays(3 - $number)->addMinutes(90), 'duration_minutes' => 90, 'status' => 'finished']);
    }

    private function league(string $name): League
    {
        return League::create(['name' => $name, 'slug' => str($name.'-'.uniqid())->slug(), 'primary_color' => '#125444', 'secondary_color' => '#d9a928', 'status' => 'active']);
    }
}
