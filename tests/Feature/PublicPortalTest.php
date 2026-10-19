<?php

namespace Tests\Feature;

use App\Domain\Competition\Models\Category;
use App\Domain\Competition\Models\Competition;
use App\Domain\Competition\Models\Division;
use App\Domain\Competition\Models\Regulation;
use App\Domain\Competition\Models\Season;
use App\Domain\Competition\Models\Tournament;
use App\Domain\Discipline\Models\Sanction;
use App\Domain\League\Models\League;
use App\Domain\Scheduling\Models\GameMatch;
use App\Domain\Scheduling\Models\MatchReport;
use App\Domain\Scheduling\Models\Matchday;
use App\Domain\Scheduling\Models\PlayingField;
use App\Domain\Scheduling\Models\Venue;
use App\Domain\Team\Models\Team;
use App\Domain\Team\Models\TeamParticipation;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class PublicPortalTest extends TestCase
{
    use RefreshDatabase;

    public function test_guests_can_open_the_directory_and_an_active_league_portal(): void
    {
        $scenario = $this->scenario();
        $scenario['league']->update(['logo_path' => 'league-logos/liga-publica.png']);

        $this->get('/')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Public/LeagueDirectory')
                ->has('leagues', 1)
                ->where('leagues.0.name', 'Liga pública')
                ->where('leagues.0.logo_url', rtrim((string) config('filesystems.disks.public.url'), '/').'/league-logos/liga-publica.png')
                ->where('leagues.0.genders.0', 'mixed')
                ->where('leagues.0.featured_competition.name', 'Primera Libre')
                ->where('leagues.0.featured_competition.division', 'Primera')
                ->where('leagues.0.featured_competition.category', 'Libre'));

        $this->get("/ligas/{$scenario['league']->slug}")
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Public/LeaguePortal')
                ->where('league.name', 'Liga pública')
                ->where('selectedCompetitionId', $scenario['competition']->id)
                ->has('schedule', 1)
                ->where('schedule.0.home_team', 'Águilas')
                ->where('schedule.0.home_crest_url', rtrim((string) config('filesystems.disks.public.url'), '/').'/team-crests/aguilas.png')
                ->where('schedule.0.field.venue', 'Unidad deportiva')
                ->where('statistics.summary.official_matches', 1)
                ->where('statistics.results.0.home_score', 2)
                ->where('activeSanctions.0.subject_name', 'Jugador sancionado')
                ->where('activeSanctions.0.matches_remaining', 1));
    }

    public function test_only_published_matchdays_and_approved_scores_are_public(): void
    {
        $scenario = $this->scenario();
        $draftMatchday = Matchday::create([
            'competition_id' => $scenario['competition']->id,
            'number' => 2,
            'name' => 'Jornada secreta',
            'phase' => 'regular',
            'status' => 'draft',
        ]);
        $draftMatch = GameMatch::create([
            'competition_id' => $scenario['competition']->id,
            'matchday_id' => $draftMatchday->id,
            'home_team_participation_id' => $scenario['home']->id,
            'away_team_participation_id' => $scenario['away']->id,
            'duration_minutes' => 90,
            'status' => 'scheduled',
            'public_notes' => 'NO PUBLICAR ESTA JORNADA',
        ]);
        MatchReport::create([
            'match_id' => $draftMatch->id,
            'status' => 'approved',
            'home_score' => 9,
            'away_score' => 8,
            'incidents' => 'INCIDENTE PRIVADO',
            'approved_at' => now(),
        ]);

        $response = $this->get("/ligas/{$scenario['league']->slug}");

        $response->assertOk()
            ->assertDontSee('NO PUBLICAR ESTA JORNADA')
            ->assertDontSee('INCIDENTE PRIVADO')
            ->assertInertia(fn (Assert $page) => $page
                ->has('schedule', 1)
                ->has('statistics.results', 1)
                ->where('statistics.summary.official_matches', 1));
    }

    public function test_private_contact_documents_and_disciplinary_details_are_not_serialized(): void
    {
        $scenario = $this->scenario();

        $response = $this->get("/ligas/{$scenario['league']->slug}");

        $response->assertOk()
            ->assertDontSee('contacto-privado@equipo.test')
            ->assertDontSee('9510000099')
            ->assertDontSee('EXPEDIENTE PRIVADO')
            ->assertDontSee('ARTÍCULO PRIVADO')
            ->assertDontSee('2500.00')
            ->assertInertia(fn (Assert $page) => $page
                ->missing('league.settings')
                ->missing('activeSanctions.0.reason')
                ->missing('activeSanctions.0.legal_basis')
                ->missing('activeSanctions.0.fine_amount'));
    }

    public function test_inactive_leagues_and_competitions_from_other_leagues_are_not_public(): void
    {
        $scenario = $this->scenario();
        $inactive = $this->league('Liga inactiva', 'inactive');
        $otherCompetition = $this->structure($this->league('Liga externa'))['competition'];

        $this->get("/ligas/{$inactive->slug}")->assertNotFound();
        $this->get("/ligas/{$scenario['league']->slug}?competition_id={$otherCompetition->id}")->assertNotFound();
        $this->get('/liga/resultados')->assertRedirect('/iniciar-sesion');
    }

    private function scenario(): array
    {
        $league = $this->league('Liga pública');
        $structure = $this->structure($league);
        $competition = $structure['competition'];
        $home = $this->participation($competition, $structure, 'Águilas', 'contacto-privado@equipo.test', '9510000099');
        $away = $this->participation($competition, $structure, 'Búhos');
        $venue = Venue::create([
            'league_id' => $league->id,
            'name' => 'Unidad deportiva',
            'address' => 'Avenida principal 100',
            'contact_name' => 'Contacto privado',
            'phone' => '9510000099',
            'status' => 'active',
        ]);
        $field = PlayingField::create(['venue_id' => $venue->id, 'name' => 'Campo 1', 'status' => 'active']);
        $matchday = Matchday::create([
            'competition_id' => $competition->id,
            'number' => 1,
            'name' => 'Jornada 1',
            'phase' => 'regular',
            'starts_on' => '2027-01-10',
            'ends_on' => '2027-01-10',
            'status' => 'published',
            'published_at' => now(),
        ]);
        $match = GameMatch::create([
            'competition_id' => $competition->id,
            'matchday_id' => $matchday->id,
            'home_team_participation_id' => $home->id,
            'away_team_participation_id' => $away->id,
            'playing_field_id' => $field->id,
            'scheduled_at' => now()->addDay(),
            'scheduled_end_at' => now()->addDay()->addMinutes(90),
            'duration_minutes' => 90,
            'status' => 'scheduled',
            'public_notes' => 'Entrada libre',
        ]);
        MatchReport::create([
            'match_id' => $match->id,
            'folio' => 'CED-PUBLICA-1',
            'status' => 'approved',
            'home_score' => 2,
            'away_score' => 1,
            'incidents' => 'INCIDENTE INTERNO',
            'approved_at' => now(),
        ]);
        Sanction::create([
            'league_id' => $league->id,
            'season_id' => $structure['season']->id,
            'competition_id' => $competition->id,
            'team_id' => $home->team_id,
            'subject_type' => 'player',
            'subject_name' => 'Jugador sancionado',
            'kind' => 'suspension',
            'source' => 'manual',
            'reason' => 'EXPEDIENTE PRIVADO',
            'legal_basis' => 'ARTÍCULO PRIVADO',
            'matches_total' => 2,
            'matches_served' => 1,
            'fine_amount' => 2500,
            'status' => 'active',
            'issued_at' => now(),
            'appeal_deadline_at' => now()->addDays(3),
        ]);

        return compact('league', 'competition', 'home', 'away');
    }

    private function structure(League $league): array
    {
        $season = Season::create(['league_id' => $league->id, 'name' => 'Temporada 2027', 'starts_on' => '2027-01-01', 'ends_on' => '2027-12-31', 'status' => 'active']);
        $tournament = Tournament::create(['season_id' => $season->id, 'name' => 'Torneo de liga', 'slug' => 'torneo-liga', 'status' => 'active']);
        $division = Division::create(['league_id' => $league->id, 'name' => 'Primera', 'slug' => 'primera', 'status' => 'active']);
        $category = Category::create(['league_id' => $league->id, 'name' => 'Libre', 'slug' => 'libre', 'status' => 'active']);
        $regulation = Regulation::create([
            'league_id' => $league->id,
            'name' => 'Reglamento general',
            'version' => 1,
            'status' => 'in_use',
            'points_win' => 3,
            'points_draw' => 1,
            'points_loss' => 0,
            'fair_play_yellow_points' => 1,
            'fair_play_second_yellow_points' => 2,
            'fair_play_red_points' => 3,
            'league_settings_snapshot' => [],
        ]);
        foreach (['points', 'goal_difference', 'goals_for', 'head_to_head', 'fair_play'] as $priority => $criterion) {
            $regulation->tiebreakers()->create(['criterion' => $criterion, 'priority' => $priority + 1]);
        }
        $competition = Competition::create([
            'tournament_id' => $tournament->id,
            'division_id' => $division->id,
            'category_id' => $category->id,
            'regulation_id' => $regulation->id,
            'name' => 'Primera Libre',
            'format' => 'round_robin',
            'regular_leg_count' => 1,
            'knockout_leg_count' => 2,
            'minimum_roster_size' => 8,
            'maximum_roster_size' => 30,
            'status' => 'active',
        ]);

        return compact('season', 'division', 'competition');
    }

    private function participation(Competition $competition, array $structure, string $name, ?string $email = null, ?string $phone = null): TeamParticipation
    {
        $team = Team::create([
            'league_id' => $structure['season']->league_id,
            'name' => $name,
            'short_name' => $name,
            'slug' => str($name.'-'.uniqid())->slug(),
            'email' => $email,
            'phone' => $phone,
            'crest_path' => 'team-crests/'.str($name)->ascii()->slug().'.png',
            'photo_path' => 'teams/private-photo.jpg',
            'status' => 'active',
        ]);

        return TeamParticipation::create([
            'team_id' => $team->id,
            'competition_id' => $competition->id,
            'season_id' => $structure['season']->id,
            'division_id' => $structure['division']->id,
            'registered_name' => $name,
            'status' => 'active',
            'requested_at' => now(),
        ]);
    }

    private function league(string $name, string $status = 'active'): League
    {
        return League::create([
            'name' => $name,
            'slug' => str($name.'-'.uniqid())->slug(),
            'primary_color' => '#125444',
            'secondary_color' => '#d9a928',
            'settings' => ['private_key' => 'NO PUBLICAR'],
            'status' => $status,
        ]);
    }
}
