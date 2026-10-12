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
use App\Domain\Scheduling\Models\MatchLineup;
use App\Domain\Scheduling\Models\MatchRefereeAssignment;
use App\Domain\Scheduling\Models\MatchReport;
use App\Domain\Scheduling\Models\Matchday;
use App\Domain\Team\Models\Team;
use App\Domain\Team\Models\TeamParticipation;
use App\Domain\Team\Models\TeamRepresentative;
use App\Support\LeagueContext;
use Database\Seeders\IdentitySeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class MatchReportManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_complete_report_flow_requires_both_representatives_and_admin_approval(): void
    {
        $s = $this->scenario();
        $payload = $this->payload($s, 1, 0);
        $this->actingAs($s['refereeUser'])->withSession($this->leagueSession($s, 'referee'))->put("/liga/partidos/{$s['match']->id}/cedula", $payload)->assertSessionHasNoErrors();
        $this->actingAs($s['refereeUser'])->withSession($this->leagueSession($s, 'referee'))->post("/liga/partidos/{$s['match']->id}/cedula/enviar", ['reason' => 'Cierre arbitral'])->assertSessionHasNoErrors();
        $report = MatchReport::firstOrFail();
        $this->assertSame('awaiting_confirmations', $report->status->value);
        $this->assertNotNull($report->folio);

        $this->actingAs($s['homeRepresentative'])->withSession($this->leagueSession($s, 'team_representative'))->post("/liga/cedulas/{$report->id}/conformidad", ['decision' => 'confirmed', 'notes' => 'Conforme'])->assertSessionHasNoErrors();
        $this->assertSame('awaiting_confirmations', $report->fresh()->status->value);
        $this->actingAs($s['awayRepresentative'])->withSession($this->leagueSession($s, 'team_representative'))->post("/liga/cedulas/{$report->id}/conformidad", ['decision' => 'confirmed', 'notes' => 'Conforme'])->assertSessionHasNoErrors();
        $this->assertSame('under_review', $report->fresh()->status->value);

        $this->actingAs($s['administrator'])->withSession($this->leagueSession($s, 'league_admin'))->post("/liga/cedulas/{$report->id}/revision", ['action' => 'approve', 'notes' => 'Resultado validado'])->assertSessionHasNoErrors();
        $this->assertSame('approved', $report->fresh()->status->value);
        $this->assertSame('finished', $s['match']->fresh()->status->value);
        $this->assertDatabaseHas('audit_logs', ['action' => 'match_report.reviewed']);
    }

    public function test_score_must_match_goal_events_and_rejection_reopens_correction(): void
    {
        $s = $this->scenario();
        $invalid = $this->payload($s, 2, 0);
        $this->actingAs($s['refereeUser'])->withSession($this->leagueSession($s, 'referee'))->put("/liga/partidos/{$s['match']->id}/cedula", $invalid)->assertSessionHasErrors('report');

        $this->actingAs($s['refereeUser'])->withSession($this->leagueSession($s, 'referee'))->put("/liga/partidos/{$s['match']->id}/cedula", $this->payload($s, 1, 0))->assertSessionHasNoErrors();
        $this->actingAs($s['refereeUser'])->withSession($this->leagueSession($s, 'referee'))->post("/liga/partidos/{$s['match']->id}/cedula/enviar", ['reason' => 'Cierre'])->assertSessionHasNoErrors();
        $report = MatchReport::firstOrFail();
        $this->actingAs($s['awayRepresentative'])->withSession($this->leagueSession($s, 'team_representative'))->post("/liga/cedulas/{$report->id}/conformidad", ['decision' => 'rejected', 'notes' => 'El minuto del gol es incorrecto'])->assertSessionHasNoErrors();
        $this->assertSame('correction_required', $report->fresh()->status->value);
        $this->actingAs($s['refereeUser'])->withSession($this->leagueSession($s, 'referee'))->put("/liga/partidos/{$s['match']->id}/cedula", $this->payload($s, 1, 0))->assertSessionHasNoErrors();
    }

    public function test_report_uses_only_players_from_the_submitted_lineup_when_available(): void
    {
        $s = $this->scenario();
        $starters = collect([$s['homePlayer']]);
        foreach (range(2, 8) as $number) {
            $starters->push($this->registration($s['league'], $s['home'], "Titular {$number}", $number));
        }
        $outsidePlayer = $this->registration($s['league'], $s['home'], 'Fuera de alineación', 20);
        $lineup = MatchLineup::create([
            'match_id' => $s['match']->id,
            'team_participation_id' => $s['home']->id,
            'status' => 'submitted',
            'submitted_at' => now(),
            'submitted_by' => $s['homeRepresentative']->id,
        ]);
        foreach ($starters->values() as $index => $registration) {
            $lineup->players()->create([
                'player_registration_id' => $registration->id,
                'role' => 'starter',
                'jersey_number' => $registration->jersey_number,
                'position' => $registration->player->position,
                'is_captain' => $index === 0,
                'display_order' => $index + 1,
            ]);
        }

        $this->actingAs($s['refereeUser'])
            ->withSession($this->leagueSession($s, 'referee'))
            ->get('/liga/cedulas')
            ->assertInertia(fn (Assert $page) => $page
                ->component('League/Reports/Index')
                ->where('matches.data.0.home.has_submitted_lineup', true)
                ->has('matches.data.0.home.players', 8)
                ->where('matches.data.0.home.players.0.lineup_role', 'starter')
                ->where('matches.data.0.home.players.0.is_captain', true));

        $payload = $this->payload($s, 1, 0);
        $payload['events'][0]['player_registration_id'] = $outsidePlayer->id;
        $this->actingAs($s['refereeUser'])
            ->withSession($this->leagueSession($s, 'referee'))
            ->put("/liga/partidos/{$s['match']->id}/cedula", $payload)
            ->assertSessionHasErrors('events');
    }

    private function payload(array $s, int $homeScore, int $awayScore): array
    {
        return ['home_score' => $homeScore, 'away_score' => $awayScore, 'home_no_show' => false, 'away_no_show' => false, 'summary' => 'Partido concluido', 'incidents' => 'Sin incidentes', 'events' => [['team_participation_id' => $s['home']->id, 'player_registration_id' => $s['homePlayer']->id, 'related_player_registration_id' => null, 'type' => 'goal', 'minute' => 20, 'period' => 'first_half', 'notes' => 'Gol']], 'reason' => 'Captura de prueba'];
    }

    private function scenario(): array
    {
        $roles = collect(['league_admin', 'referee', 'team_representative'])->mapWithKeys(fn ($slug) => [$slug => Role::where('slug', $slug)->firstOrFail()]);
        $league = League::create(['name' => 'Liga Taladzi', 'slug' => 'liga-'.uniqid(), 'primary_color' => '#125444', 'secondary_color' => '#d9a928', 'status' => 'active']);
        $administrator = User::factory()->create(); $refereeUser = User::factory()->create(); $homeRepresentative = User::factory()->create(); $awayRepresentative = User::factory()->create();
        foreach ([[$administrator, 'league_admin'], [$refereeUser, 'referee'], [$homeRepresentative, 'team_representative'], [$awayRepresentative, 'team_representative']] as [$user, $role]) $this->membership($league, $user, $roles[$role]);
        $season = Season::create(['league_id' => $league->id, 'name' => '2027', 'starts_on' => '2027-01-01', 'ends_on' => '2027-12-31', 'status' => 'active']);
        $tournament = Tournament::create(['season_id' => $season->id, 'name' => 'Liga', 'slug' => 'liga-'.uniqid(), 'status' => 'active']);
        $division = Division::create(['league_id' => $league->id, 'name' => 'Primera', 'slug' => 'primera-'.uniqid(), 'status' => 'active']);
        $category = Category::create(['league_id' => $league->id, 'name' => 'Libre', 'slug' => 'libre-'.uniqid(), 'status' => 'active']);
        $regulation = Regulation::create(['league_id' => $league->id, 'name' => 'General', 'version' => 1, 'status' => 'in_use', 'league_settings_snapshot' => []]);
        $competition = Competition::create(['tournament_id' => $tournament->id, 'division_id' => $division->id, 'category_id' => $category->id, 'regulation_id' => $regulation->id, 'name' => 'Primera Libre', 'format' => 'round_robin', 'regular_leg_count' => 1, 'knockout_leg_count' => 2, 'minimum_roster_size' => 1, 'maximum_roster_size' => 30, 'status' => 'active']);
        $home = $this->participation($league, $competition, $season, $division, 'Locales'); $away = $this->participation($league, $competition, $season, $division, 'Visitantes');
        TeamRepresentative::create(['team_id' => $home->team_id, 'league_membership_id' => $homeRepresentative->leagueMemberships()->first()->id, 'user_id' => $homeRepresentative->id, 'photo_path' => 'private/home-photo.png', 'ine_path' => 'private/home-ine.png', 'status' => 'active', 'started_at' => now(), 'assigned_by' => $administrator->id]);
        TeamRepresentative::create(['team_id' => $away->team_id, 'league_membership_id' => $awayRepresentative->leagueMemberships()->first()->id, 'user_id' => $awayRepresentative->id, 'photo_path' => 'private/away-photo.png', 'ine_path' => 'private/away-ine.png', 'status' => 'active', 'started_at' => now(), 'assigned_by' => $administrator->id]);
        $matchday = Matchday::create(['competition_id' => $competition->id, 'number' => 1, 'name' => 'Jornada 1', 'phase' => 'regular', 'starts_on' => '2027-01-04', 'ends_on' => '2027-01-04', 'status' => 'published', 'published_at' => now()]);
        $match = GameMatch::create(['competition_id' => $competition->id, 'matchday_id' => $matchday->id, 'home_team_participation_id' => $home->id, 'away_team_participation_id' => $away->id, 'scheduled_at' => '2027-01-04 10:00:00', 'duration_minutes' => 90, 'status' => 'scheduled']);
        $referee = Referee::create(['league_id' => $league->id, 'user_id' => $refereeUser->id, 'category_level' => 'Estatal', 'status' => 'active']);
        MatchRefereeAssignment::create(['match_id' => $match->id, 'referee_id' => $referee->id, 'role' => 'central', 'assigned_by' => $administrator->id]);
        $homePlayer = $this->registration($league, $home, 'Goleador', 9); $awayPlayer = $this->registration($league, $away, 'Defensa', 4);
        return compact('roles', 'league', 'administrator', 'refereeUser', 'homeRepresentative', 'awayRepresentative', 'home', 'away', 'match', 'homePlayer', 'awayPlayer');
    }

    protected function setUp(): void { parent::setUp(); $this->seed(IdentitySeeder::class); }
    private function participation(League $league, Competition $competition, Season $season, Division $division, string $name): TeamParticipation { $team = Team::create(['league_id' => $league->id, 'name' => $name, 'short_name' => $name, 'slug' => str($name.uniqid())->slug(), 'status' => 'active']); return TeamParticipation::create(['team_id' => $team->id, 'competition_id' => $competition->id, 'season_id' => $season->id, 'division_id' => $division->id, 'registered_name' => $name, 'status' => 'active', 'requested_at' => now()]); }
    private function registration(League $league, TeamParticipation $participation, string $name, int $number): PlayerRegistration { $player = Player::create(['league_id' => $league->id, 'full_name' => $name, 'birth_date' => '2000-01-01', 'gender' => 'unspecified', 'position' => 'forward', 'emergency_contact_name' => 'Contacto', 'emergency_contact_phone' => '9510000000', 'emergency_contact_relationship' => 'Familiar', 'photo_path' => 'players/test.png', 'photo_hash' => hash('sha256', $name), 'status' => 'active']); return PlayerRegistration::create(['player_id' => $player->id, 'team_id' => $participation->team_id, 'team_participation_id' => $participation->id, 'competition_id' => $participation->competition_id, 'season_id' => $participation->season_id, 'division_id' => $participation->division_id, 'jersey_number' => $number, 'status' => 'active', 'requested_at' => now()]); }
    private function membership(League $league, User $user, Role $role): LeagueMembership { return LeagueMembership::create(['league_id' => $league->id, 'user_id' => $user->id, 'role_id' => $role->id, 'status' => 'active', 'started_at' => now()]); }
    private function leagueSession(array $s, string $role): array { return [LeagueContext::LEAGUE_KEY => $s['league']->id, LeagueContext::ROLE_KEY => $s['roles'][$role]->id]; }
}
