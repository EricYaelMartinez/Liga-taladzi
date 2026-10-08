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
use App\Domain\Referee\Models\Referee;
use App\Domain\Scheduling\Models\GameMatch;
use App\Domain\Scheduling\Models\Matchday;
use App\Domain\Scheduling\Models\PlayingField;
use App\Domain\Scheduling\Models\ScheduleTimeSlot;
use App\Domain\Scheduling\Models\Venue;
use App\Domain\Scheduling\Services\RoundRobinScheduleGenerator;
use App\Domain\Team\Models\Team;
use App\Domain\Team\Models\TeamParticipation;
use App\Support\LeagueContext;
use Carbon\Carbon;
use Database\Seeders\IdentitySeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ScheduleManagementTest extends TestCase
{
    use RefreshDatabase;

    private Role $adminRole;
    private Role $refereeRole;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(IdentitySeeder::class);
        $this->adminRole = Role::where('slug', 'league_admin')->firstOrFail();
        $this->refereeRole = Role::where('slug', 'referee')->firstOrFail();
    }

    public function test_match_timestamps_include_the_timezone_when_written(): void
    {
        $this->assertSame('Y-m-d H:i:s P', (new GameMatch())->getDateFormat());
    }

    public function test_generator_creates_rounds_for_odd_number_of_teams_without_fake_match(): void
    {
        [$administrator, $league] = $this->administrator();
        $competition = $this->competition($league, 'round_robin');
        foreach (range(1, 3) as $number) $this->participation($competition, "Equipo {$number}");

        $created = app(RoundRobinScheduleGenerator::class)->generate($competition->load('regulation'), Carbon::parse('2027-01-03'), 7, $administrator->id);

        $this->assertCount(3, $created);
        $this->assertDatabaseCount('matches', 3);
        foreach ($competition->teamParticipations as $participation) {
            $appearances = GameMatch::where('home_team_participation_id', $participation->id)->orWhere('away_team_participation_id', $participation->id)->count();
            $this->assertSame(2, $appearances);
        }
    }

    public function test_double_round_robin_reverses_home_and_away_fixtures(): void
    {
        [$administrator, $league] = $this->administrator();
        $competition = $this->competition($league, 'double_round_robin');
        $first = $this->participation($competition, 'Azules');
        $second = $this->participation($competition, 'Dorados');

        app(RoundRobinScheduleGenerator::class)->generate($competition->load('regulation'), Carbon::parse('2027-01-03'), 7, $administrator->id);

        $this->assertDatabaseHas('matches', ['home_team_participation_id' => $first->id, 'away_team_participation_id' => $second->id]);
        $this->assertDatabaseHas('matches', ['home_team_participation_id' => $second->id, 'away_team_participation_id' => $first->id]);
    }

    public function test_generator_can_create_only_the_next_round_without_repeating_matches(): void
    {
        [$administrator, $league] = $this->administrator();
        $competition = $this->competition($league, 'round_robin');
        foreach (range(1, 4) as $number) $this->participation($competition, "Equipo {$number}");

        app(RoundRobinScheduleGenerator::class)->generate($competition->load('regulation'), Carbon::parse('2027-01-04'), 7, $administrator->id, 'next');
        $this->assertDatabaseCount('matchdays', 1);
        $this->assertDatabaseCount('matches', 2);

        app(RoundRobinScheduleGenerator::class)->generate($competition->load('regulation'), Carbon::parse('2027-01-11'), 7, $administrator->id, 'next');
        $this->assertDatabaseCount('matchdays', 2);
        $this->assertDatabaseCount('matches', 4);
        $this->assertSame(4, GameMatch::query()->distinct()->count('pairing_key'));
    }

    public function test_generator_assigns_standard_time_and_field_without_requiring_referee(): void
    {
        [$administrator, $league] = $this->administrator();
        $competition = $this->competition($league, 'round_robin');
        $this->participation($competition, 'Locales');
        $this->participation($competition, 'Visitantes');
        $field = $this->field($league, 'Cancha automática');
        ScheduleTimeSlot::create(['league_id' => $league->id, 'weekday' => 1, 'starts_at' => '10:00', 'created_by' => $administrator->id]);

        app(RoundRobinScheduleGenerator::class)->generate(
            $competition->load('regulation', 'tournament.season'), Carbon::parse('2027-01-04'), 7,
            $administrator->id, 'all', true, 'Programación automática',
        );

        $match = GameMatch::firstOrFail();
        $this->assertSame($field->id, $match->playing_field_id);
        $this->assertNotNull($match->scheduled_at);
        $this->assertSame('10:00', $match->scheduled_at->copy()->setTimezone(config('app.timezone'))->format('H:i'));
        $this->assertDatabaseMissing('match_referee_assignments', ['match_id' => $match->id]);
    }

    public function test_administrator_programs_match_when_field_and_referee_are_available(): void
    {
        [$administrator, $league] = $this->administrator();
        [$matchday, $match] = $this->fixture($league);
        $field = $this->field($league, 'Cancha 1');
        $referee = $this->referee($league, 'Árbitro Central');

        $this->actingAs($administrator)->withSession($this->leagueSession($league, $this->adminRole))
            ->post("/liga/partidos/{$match->id}/programar", $this->programPayload($field, $referee))
            ->assertSessionHasNoErrors();

        $this->assertDatabaseHas('matches', ['id' => $match->id, 'playing_field_id' => $field->id, 'status' => 'draft']);
        $this->assertDatabaseHas('match_referee_assignments', ['match_id' => $match->id, 'referee_id' => $referee->id, 'role' => 'central']);
        $this->assertDatabaseHas('match_schedule_changes', ['match_id' => $match->id, 'type' => 'scheduled']);
    }

    public function test_schedule_and_referee_assignment_can_be_saved_in_separate_steps(): void
    {
        [$administrator, $league] = $this->administrator();
        [, $match] = $this->fixture($league);
        $field = $this->field($league, 'Cancha separada');
        $referee = $this->referee($league, 'Árbitro separado');
        $session = $this->leagueSession($league, $this->adminRole);

        $this->actingAs($administrator)->withSession($session)->put("/liga/partidos/{$match->id}/programacion", [
            'scheduled_at' => '2027-01-04T10:00', 'playing_field_id' => $field->id,
            'public_notes' => '', 'reason' => 'Definición de horario',
        ])->assertSessionHasNoErrors();
        $match->refresh();
        $this->assertSame('10:00', $match->scheduled_at->copy()->setTimezone(config('app.timezone'))->format('H:i'));
        $this->assertDatabaseMissing('match_referee_assignments', ['match_id' => $match->id]);

        $this->actingAs($administrator)->withSession($session)->put("/liga/partidos/{$match->id}/arbitros", [
            'central_referee_id' => $referee->id, 'assistant_1_referee_id' => null,
            'assistant_2_referee_id' => null, 'fourth_referee_id' => null, 'reason' => 'Designación semanal',
        ])->assertSessionHasNoErrors();
        $this->assertDatabaseHas('match_referee_assignments', ['match_id' => $match->id, 'referee_id' => $referee->id, 'role' => 'central']);
        $this->assertDatabaseHas('match_schedule_changes', ['match_id' => $match->id, 'type' => 'referees_assigned']);
    }

    public function test_field_daily_limit_is_enforced_when_reprogramming(): void
    {
        [$administrator, $league] = $this->administrator();
        $competition = $this->competition($league);
        $teams = collect(range(1, 4))->map(fn ($number) => $this->participation($competition, "Equipo límite {$number}"));
        $matchday = $this->matchday($competition);
        $first = $this->match($matchday, $teams[0], $teams[1]);
        $second = $this->match($matchday, $teams[2], $teams[3]);
        $field = $this->field($league, 'Cancha limitada');
        $field->update(['max_matches_per_day' => 1]);
        $referee = $this->referee($league, 'Árbitro 1');
        $other = $this->referee($league, 'Árbitro 2');
        $session = $this->leagueSession($league, $this->adminRole);
        $this->actingAs($administrator)->withSession($session)->post("/liga/partidos/{$first->id}/programar", $this->programPayload($field, $referee))->assertSessionHasNoErrors();

        $payload = $this->programPayload($field, $other);
        $payload['scheduled_at'] = '2027-01-04T14:00';
        $this->actingAs($administrator)->withSession($session)->post("/liga/partidos/{$second->id}/programar", $payload)
            ->assertSessionHasErrors('playing_field_id');
    }

    public function test_conflicts_for_field_team_and_referee_are_rejected(): void
    {
        [$administrator, $league] = $this->administrator();
        $competition = $this->competition($league);
        $teams = collect(range(1, 4))->map(fn ($number) => $this->participation($competition, "Equipo {$number}"));
        $matchday = $this->matchday($competition);
        $first = $this->match($matchday, $teams[0], $teams[1]);
        $second = $this->match($matchday, $teams[2], $teams[3]);
        $field = $this->field($league, 'Cancha 1');
        $otherField = $this->field($league, 'Cancha 2');
        $referee = $this->referee($league, 'Árbitro 1');
        $otherReferee = $this->referee($league, 'Árbitro 2');
        $session = $this->leagueSession($league, $this->adminRole);
        $this->actingAs($administrator)->withSession($session)->post("/liga/partidos/{$first->id}/programar", $this->programPayload($field, $referee))->assertSessionHasNoErrors();

        $this->actingAs($administrator)->withSession($session)->post("/liga/partidos/{$second->id}/programar", $this->programPayload($field, $otherReferee))->assertSessionHasErrors('playing_field_id');
        $this->actingAs($administrator)->withSession($session)->post("/liga/partidos/{$second->id}/programar", $this->programPayload($otherField, $referee))->assertSessionHasErrors('central_referee_id');

        $thirdDay = $this->matchday($competition, 2);
        $teamConflict = $this->match($thirdDay, $teams[0], $teams[2]);
        $this->actingAs($administrator)->withSession($session)->post("/liga/partidos/{$teamConflict->id}/programar", $this->programPayload($otherField, $otherReferee))->assertSessionHasErrors('scheduled_at');
    }

    public function test_regular_phase_rejects_assistant_referees(): void
    {
        [$administrator, $league] = $this->administrator();
        [, $match] = $this->fixture($league);
        $field = $this->field($league, 'Cancha 1');
        $central = $this->referee($league, 'Central');
        $assistant = $this->referee($league, 'Asistente');
        $payload = $this->programPayload($field, $central);
        $payload['assistant_1_referee_id'] = $assistant->id;

        $this->actingAs($administrator)->withSession($this->leagueSession($league, $this->adminRole))
            ->post("/liga/partidos/{$match->id}/programar", $payload)
            ->assertSessionHasErrors('assistant_1_referee_id');
    }

    public function test_matchday_cannot_be_published_until_every_match_is_complete(): void
    {
        [$administrator, $league] = $this->administrator();
        [$matchday, $match] = $this->fixture($league);
        $session = $this->leagueSession($league, $this->adminRole);
        $this->actingAs($administrator)->withSession($session)->post("/liga/jornadas/{$matchday->id}/publicar", ['reason' => 'Publicación semanal'])->assertSessionHasErrors('matchday');

        $field = $this->field($league, 'Cancha 1');
        $referee = $this->referee($league, 'Árbitro Central');
        $this->actingAs($administrator)->withSession($session)->post("/liga/partidos/{$match->id}/programar", $this->programPayload($field, $referee));
        $this->actingAs($administrator)->withSession($session)->post("/liga/jornadas/{$matchday->id}/publicar", ['reason' => 'Publicación semanal'])->assertSessionHasNoErrors();

        $this->assertDatabaseHas('matchdays', ['id' => $matchday->id, 'status' => 'published']);
        $this->assertDatabaseHas('matches', ['id' => $match->id, 'status' => 'scheduled']);
    }

    public function test_referee_cannot_be_replaced_after_match_was_suspended(): void
    {
        [$administrator, $league] = $this->administrator();
        [$matchday, $match] = $this->fixture($league);
        $field = $this->field($league, 'Cancha 1');
        $original = $this->referee($league, 'Árbitro Original');
        $replacement = $this->referee($league, 'Árbitro Reemplazo');
        $session = $this->leagueSession($league, $this->adminRole);
        $this->actingAs($administrator)->withSession($session)->post("/liga/partidos/{$match->id}/programar", $this->programPayload($field, $original));
        $matchday->update(['status' => 'published']);
        $match->update(['status' => 'suspended']);

        $this->actingAs($administrator)->withSession($session)->post("/liga/partidos/{$match->id}/programar", $this->programPayload($field, $replacement))
            ->assertSessionHasErrors('central_referee_id');
    }

    public function test_referee_can_view_only_published_schedule_and_cannot_manage_it(): void
    {
        $league = $this->league();
        $referee = $this->referee($league, 'Árbitro Consulta');
        $competition = $this->competition($league);
        $draft = $this->matchday($competition, 1, 'BORRADOR-OCULTO');
        $published = $this->matchday($competition, 2, 'JORNADA-PUBLICA');
        $published->update(['status' => 'published', 'published_at' => now()]);
        $session = $this->leagueSession($league, $this->refereeRole);

        $this->actingAs($referee->user)->withSession($session)->get('/liga/calendario')->assertOk()->assertSee('JORNADA-PUBLICA')->assertDontSee('BORRADOR-OCULTO');
        $this->actingAs($referee->user)->withSession($session)->post('/liga/jornadas', [])->assertForbidden();
    }

    public function test_suspending_team_cancels_its_future_matches(): void
    {
        [$administrator, $league] = $this->administrator();
        [$matchday, $match] = $this->fixture($league);
        $participation = $match->homeParticipation;
        $matchday->update(['status' => 'published']);
        $match->update(['status' => 'scheduled', 'scheduled_at' => now()->addWeek(), 'scheduled_end_at' => now()->addWeek()->addMinutes(105)]);

        $this->actingAs($administrator)->withSession($this->leagueSession($league, $this->adminRole))
            ->put("/liga/participaciones/{$participation->id}/estado", ['action' => 'suspend', 'reason' => 'Falta de pago'])
            ->assertSessionHasNoErrors();

        $this->assertDatabaseHas('matches', ['id' => $match->id, 'status' => 'cancelled']);
        $this->assertDatabaseHas('match_schedule_changes', ['match_id' => $match->id, 'type' => 'cancelled']);
    }

    private function administrator(): array
    {
        $league = $this->league();
        $user = User::factory()->create();
        $this->membership($league, $user, $this->adminRole);
        return [$user, $league];
    }

    private function league(): League
    {
        $league = League::create(['name' => 'Liga '.uniqid(), 'slug' => 'liga-'.uniqid(), 'primary_color' => '#125444', 'secondary_color' => '#d9a928', 'status' => 'active']);
        LeagueSetting::create(['league_id' => $league->id, 'match_periods' => 2, 'period_duration_minutes' => 45, 'halftime_minutes' => 15, 'schedule_buffer_minutes' => 15, 'appeal_deadline_hours' => 2, 'reactivation_window_days' => 21, 'currency' => 'MXN']);
        return $league;
    }

    private function competition(League $league, string $format = 'round_robin'): Competition
    {
        $season = Season::create(['league_id' => $league->id, 'name' => 'Temporada '.uniqid(), 'starts_on' => '2027-01-01', 'ends_on' => '2027-12-31', 'status' => 'active']);
        $tournament = Tournament::create(['season_id' => $season->id, 'name' => 'Torneo', 'slug' => 'torneo-'.uniqid(), 'status' => 'active']);
        $division = Division::create(['league_id' => $league->id, 'name' => 'Primera '.uniqid(), 'slug' => 'primera-'.uniqid(), 'status' => 'active']);
        $category = Category::create(['league_id' => $league->id, 'name' => 'Libre '.uniqid(), 'slug' => 'libre-'.uniqid(), 'status' => 'active']);
        $regulation = Regulation::create(['league_id' => $league->id, 'name' => 'Reglamento '.uniqid(), 'version' => 1, 'status' => 'in_use', 'league_settings_snapshot' => ['match_periods' => 2, 'period_duration_minutes' => 45, 'halftime_minutes' => 15, 'schedule_buffer_minutes' => 15]]);
        return Competition::create(['tournament_id' => $tournament->id, 'division_id' => $division->id, 'category_id' => $category->id, 'regulation_id' => $regulation->id, 'name' => 'Competencia '.uniqid(), 'format' => $format, 'regular_leg_count' => $format === 'double_round_robin' ? 2 : 1, 'knockout_leg_count' => 2, 'minimum_roster_size' => 1, 'maximum_roster_size' => 30, 'status' => 'active']);
    }

    private function participation(Competition $competition, string $name): TeamParticipation
    {
        $leagueId = $competition->tournament->season->league_id;
        $team = Team::create(['league_id' => $leagueId, 'name' => $name, 'short_name' => $name, 'slug' => str($name.'-'.uniqid())->slug(), 'status' => 'active']);
        return TeamParticipation::create(['team_id' => $team->id, 'competition_id' => $competition->id, 'season_id' => $competition->tournament->season_id, 'division_id' => $competition->division_id, 'registered_name' => $name, 'status' => 'active', 'requested_at' => now()]);
    }

    private function matchday(Competition $competition, int $number = 1, ?string $name = null): Matchday
    {
        return Matchday::create(['competition_id' => $competition->id, 'number' => $number, 'name' => $name ?? "Jornada {$number}", 'phase' => 'regular', 'starts_on' => '2027-01-04', 'ends_on' => '2027-01-04', 'status' => 'draft']);
    }

    private function match(Matchday $matchday, TeamParticipation $home, TeamParticipation $away): GameMatch
    {
        return GameMatch::create(['competition_id' => $matchday->competition_id, 'matchday_id' => $matchday->id, 'home_team_participation_id' => $home->id, 'away_team_participation_id' => $away->id, 'duration_minutes' => 105, 'status' => 'draft']);
    }

    private function fixture(League $league): array
    {
        $competition = $this->competition($league);
        $matchday = $this->matchday($competition);
        return [$matchday, $this->match($matchday, $this->participation($competition, 'Locales'), $this->participation($competition, 'Visitantes'))];
    }

    private function field(League $league, string $name): PlayingField
    {
        $venue = Venue::create(['league_id' => $league->id, 'name' => 'Unidad '.uniqid(), 'address' => 'Centro', 'status' => 'active']);
        $field = PlayingField::create(['venue_id' => $venue->id, 'name' => $name, 'surface' => 'synthetic', 'status' => 'active']);
        $field->availabilities()->create(['weekday' => 1, 'starts_at' => '08:00', 'ends_at' => '18:00']);
        return $field;
    }

    private function referee(League $league, string $name): Referee
    {
        $user = User::factory()->create(['name' => $name]);
        $this->membership($league, $user, $this->refereeRole);
        $referee = Referee::create(['league_id' => $league->id, 'user_id' => $user->id, 'category_level' => 'Estatal', 'status' => 'active']);
        $referee->availabilities()->create(['weekday' => 1, 'starts_at' => '08:00', 'ends_at' => '18:00']);
        return $referee;
    }

    private function programPayload(PlayingField $field, Referee $referee): array
    {
        return ['scheduled_at' => '2027-01-04T10:00', 'playing_field_id' => $field->id, 'central_referee_id' => $referee->id, 'assistant_1_referee_id' => null, 'assistant_2_referee_id' => null, 'fourth_referee_id' => null, 'public_notes' => '', 'reason' => 'Programación semanal'];
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
