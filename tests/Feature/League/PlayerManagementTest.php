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
use App\Domain\Player\Models\PlayerCredential;
use App\Domain\Player\Models\PlayerDocument;
use App\Domain\Team\Models\Team;
use App\Domain\Team\Models\TeamParticipation;
use App\Domain\Team\Models\TeamRepresentative;
use App\Support\LeagueContext;
use Database\Seeders\IdentitySeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class PlayerManagementTest extends TestCase
{
    use RefreshDatabase;

    private Role $adminRole;
    private Role $representativeRole;
    private Role $playerRole;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
        Storage::fake('public');
        $this->seed(IdentitySeeder::class);
        $this->adminRole = Role::where('slug', 'league_admin')->firstOrFail();
        $this->representativeRole = Role::where('slug', 'team_representative')->firstOrFail();
        $this->playerRole = Role::where('slug', 'player')->firstOrFail();
    }

    public function test_representative_registers_player_without_system_account(): void
    {
        [$administrator, $representative, $league, $participation] = $this->scenario();

        $this->actingAs($representative)->withSession($this->leagueSession($league, $this->representativeRole))
            ->post('/liga/jugadores', $this->playerPayload($participation, 'Jugador Uno', 10))
            ->assertSessionHasNoErrors();

        $player = Player::firstOrFail();
        $this->assertNull($player->user_id);
        $this->assertSame('pending', $player->status->value);
        $this->assertDatabaseHas('player_registrations', ['player_id' => $player->id, 'jersey_number' => 10, 'status' => 'pending']);
    }

    public function test_minor_requires_guardian_and_signed_consent(): void
    {
        [, $representative, $league, $participation] = $this->scenario();
        $payload = $this->playerPayload($participation, 'Jugador Menor', 11);
        $payload['birth_date'] = now()->subYears(15)->toDateString();

        $this->actingAs($representative)->withSession($this->leagueSession($league, $this->representativeRole))
            ->post('/liga/jugadores', $payload)
            ->assertSessionHasErrors(['guardian_name', 'guardian_phone', 'guardian_consent']);

        $payload['guardian_name'] = 'Tutor Responsable';
        $payload['guardian_phone'] = '9510001111';
        $payload['photo'] = $this->fakeImage('jugador.png');
        $payload['guardian_consent'] = UploadedFile::fake()->create('carta.pdf', 20, 'application/pdf');
        $this->actingAs($representative)->withSession($this->leagueSession($league, $this->representativeRole))
            ->post('/liga/jugadores', $payload)->assertSessionHasNoErrors();
        $this->assertDatabaseHas('player_documents', ['type' => 'guardian_consent']);
    }

    public function test_exact_duplicate_is_rejected_by_name_birth_date_and_photo(): void
    {
        [, $representative, $league, $participation] = $this->scenario();
        $session = $this->leagueSession($league, $this->representativeRole);
        $this->actingAs($representative)->withSession($session)->post('/liga/jugadores', $this->playerPayload($participation, 'Jugador Repetido', 8));

        $this->actingAs($representative)->withSession($session)
            ->post('/liga/jugadores', $this->playerPayload($participation, 'JUGADOR REPETIDO', 9))
            ->assertSessionHasErrors('full_name');
        $this->assertSame(1, Player::count());
    }

    public function test_jersey_number_is_unique_inside_active_roster(): void
    {
        [, $representative, $league, $participation] = $this->scenario();
        $session = $this->leagueSession($league, $this->representativeRole);
        $this->actingAs($representative)->withSession($session)->post('/liga/jugadores', $this->playerPayload($participation, 'Jugador Uno', 7));

        $this->actingAs($representative)->withSession($session)
            ->post('/liga/jugadores', $this->playerPayload($participation, 'Jugador Dos', 7, '1999-02-02'))
            ->assertSessionHasErrors('jersey_number');
    }

    public function test_player_cannot_belong_to_two_teams_in_same_season(): void
    {
        [$administrator, , $league, $participation] = $this->scenario();
        $second = $this->secondParticipation($league, $administrator, $participation->season);
        $player = $this->player($league, 'Jugador Activo');
        $this->registration($player, $participation, 'active', 4);

        $this->actingAs($administrator)->withSession($this->leagueSession($league, $this->adminRole))
            ->post("/liga/jugadores/{$player->id}/plantillas", ['team_participation_id' => $second->id, 'jersey_number' => 12, 'reason' => 'Intento duplicado'])
            ->assertSessionHasErrors('player_id');
    }

    public function test_administrator_approves_and_releases_player_preserving_history(): void
    {
        [$administrator, $representative, $league, $participation] = $this->scenario();
        $this->actingAs($representative)->withSession($this->leagueSession($league, $this->representativeRole))
            ->post('/liga/jugadores', $this->playerPayload($participation, 'Jugador Histórico', 15));
        $registration = PlayerRegistration::firstOrFail();
        $session = $this->leagueSession($league, $this->adminRole);

        $this->actingAs($administrator)->withSession($session)
            ->put("/liga/plantillas/{$registration->id}/estado", ['action' => 'approve', 'reason' => 'Documentación correcta'])
            ->assertSessionHasNoErrors();
        $this->assertSame('active', $registration->fresh()->status->value);
        $credential = PlayerCredential::where('player_registration_id', $registration->id)->firstOrFail();
        $this->assertSame('active', $credential->status->value);
        $this->assertMatchesRegularExpression('/^L\d{4}-2027-\d{6}$/', $credential->folio);

        $this->actingAs($administrator)->withSession($session)
            ->put("/liga/plantillas/{$registration->id}/baja", ['reason' => 'Baja solicitada'])
            ->assertSessionHasNoErrors();
        $this->assertSame('released', $registration->fresh()->status->value);
        $this->assertSame('revoked', $credential->fresh()->status->value);
        $this->assertNotNull($credential->fresh()->revoked_at);
        $this->assertDatabaseHas('player_movements', ['player_id' => $registration->player_id, 'type' => 'approved']);
        $this->assertDatabaseHas('player_movements', ['player_id' => $registration->player_id, 'type' => 'released']);
    }

    public function test_representative_cannot_register_player_for_another_team(): void
    {
        [$administrator, $representative, $league, $participation] = $this->scenario();
        $other = $this->secondParticipation($league, $administrator, $participation->season);
        $payload = $this->playerPayload($other, 'Jugador Ajeno', 20);

        $this->actingAs($representative)->withSession($this->leagueSession($league, $this->representativeRole))
            ->post('/liga/jugadores', $payload)->assertForbidden();
    }

    public function test_linked_player_only_sees_own_profile(): void
    {
        [, , $league, $participation] = $this->scenario();
        $user = User::factory()->create();
        $this->membership($league, $user, $this->playerRole);
        $own = $this->player($league, 'Perfil Propio', $user->id);
        $other = $this->player($league, 'Perfil Ajeno');
        $this->registration($own, $participation, 'active', 2);
        $this->registration($other, $participation, 'released', 3);

        $this->actingAs($user)->withSession($this->leagueSession($league, $this->playerRole))
            ->get('/liga/jugadores')->assertOk()
            ->assertInertia(fn (Assert $page) => $page->component('League/Players/Index')->has('players', 1)->where('players.0.id', $own->id));
    }

    public function test_private_photo_is_only_available_to_administrator(): void
    {
        [$administrator, $representative, $league] = $this->scenario();
        $player = $this->player($league, 'Jugador Privado');
        Storage::disk('local')->put('players/photos/private.png', 'image');
        $player->update(['photo_path' => 'players/photos/private.png']);

        $this->actingAs($representative)->withSession($this->leagueSession($league, $this->representativeRole))
            ->get("/liga/jugadores/{$player->id}/archivo/photo")->assertForbidden();
        $this->actingAs($administrator)->withSession($this->leagueSession($league, $this->adminRole))
            ->get("/liga/jugadores/{$player->id}/archivo/photo")->assertOk();
    }

    public function test_credential_preview_only_contains_active_approved_players(): void
    {
        [$administrator, , $league, $participation] = $this->scenario();
        $included = $this->player($league, 'Jugador Aprobado');
        $pending = $this->player($league, 'Jugador Pendiente');
        $inactive = $this->player($league, 'Jugador Inactivo');
        $inactive->update(['status' => 'inactive']);
        $this->registration($included, $participation, 'active', 7);
        $this->registration($pending, $participation, 'pending', 8);
        $this->registration($inactive, $participation, 'active', 9);

        $this->actingAs($administrator)->withSession($this->leagueSession($league, $this->adminRole))
            ->get("/liga/plantillas/{$participation->id}/credenciales")
            ->assertOk()
            ->assertSee('Jugador Aprobado')
            ->assertDontSee('Jugador Pendiente')
            ->assertDontSee('Jugador Inactivo');
    }

    public function test_administrator_downloads_one_pdf_for_the_team(): void
    {
        [$administrator, , $league, $participation] = $this->scenario();
        $player = $this->player($league, 'Jugador PDF');
        $this->registration($player, $participation, 'active', 11);

        $this->actingAs($administrator)->withSession($this->leagueSession($league, $this->adminRole))
            ->get("/liga/plantillas/{$participation->id}/credenciales.pdf")
            ->assertOk()
            ->assertHeader('content-type', 'application/pdf')
            ->assertDownload('credenciales-equipo-local-temporada-2027.pdf');
    }

    public function test_representative_cannot_preview_or_download_team_credentials(): void
    {
        [, $representative, $league, $participation] = $this->scenario();
        $player = $this->player($league, 'Jugador Restringido');
        $this->registration($player, $participation, 'active', 12);
        $session = $this->leagueSession($league, $this->representativeRole);

        $this->actingAs($representative)->withSession($session)
            ->get("/liga/plantillas/{$participation->id}/credenciales")
            ->assertForbidden();
        $this->actingAs($representative)->withSession($session)
            ->get("/liga/plantillas/{$participation->id}/credenciales.pdf")
            ->assertForbidden();
    }

    public function test_administrator_issues_missing_credentials_without_duplicates(): void
    {
        [$administrator, , $league, $participation] = $this->scenario();
        $player = $this->player($league, 'Jugador Con Folio');
        PlayerRegistration::create(['player_id' => $player->id, 'team_id' => $participation->team_id, 'team_participation_id' => $participation->id, 'competition_id' => $participation->competition_id, 'season_id' => $participation->season_id, 'division_id' => $participation->division_id, 'jersey_number' => 18, 'status' => 'active', 'requested_at' => now()]);
        $session = $this->leagueSession($league, $this->adminRole);

        $this->actingAs($administrator)->withSession($session)
            ->post("/liga/plantillas/{$participation->id}/credenciales/emitir", ['reason' => 'Emisión inicial'])
            ->assertSessionHasNoErrors();
        $this->actingAs($administrator)->withSession($session)
            ->post("/liga/plantillas/{$participation->id}/credenciales/emitir", ['reason' => 'Verificación'])
            ->assertSessionHasNoErrors();

        $this->assertSame(1, PlayerCredential::count());
        $this->assertDatabaseHas('audit_logs', ['action' => 'player.credential.issued']);
    }

    public function test_private_guardian_document_is_validated_audited_and_retained(): void
    {
        [$administrator, $representative, $league, $participation] = $this->scenario();
        $player = $this->player($league, 'Jugador Menor Documento');
        $player->update(['birth_date' => now()->subYears(15)->toDateString()]);
        $this->registration($player, $participation, 'pending', 22);
        $session = $this->leagueSession($league, $this->adminRole);

        $this->actingAs($administrator)->withSession($session)
            ->post("/liga/jugadores/{$player->id}/carta-responsiva", [
                'document' => UploadedFile::fake()->create('carta.pdf', 100, 'application/pdf'),
                'reason' => 'Documento actualizado',
            ])->assertSessionHasNoErrors();
        $document = PlayerDocument::firstOrFail();
        $this->assertSame('carta.pdf', $document->original_name);
        $this->assertSame($participation->season->ends_on->toDateString(), $document->retain_until->toDateString());

        $this->actingAs($representative)->withSession($this->leagueSession($league, $this->representativeRole))
            ->get("/liga/documentos-jugador/{$document->id}")->assertForbidden();
        $this->actingAs($administrator)->withSession($session)
            ->get("/liga/documentos-jugador/{$document->id}")->assertOk();
        $this->assertDatabaseHas('audit_logs', ['action' => 'player.document.downloaded', 'auditable_id' => $document->id]);

        $this->actingAs($administrator)->withSession($session)
            ->delete("/liga/documentos-jugador/{$document->id}", ['reason' => 'Depuración'])
            ->assertSessionHasErrors('document');
        $document->update(['retain_until' => now()->subDay()]);
        $this->actingAs($administrator)->withSession($session)
            ->delete("/liga/documentos-jugador/{$document->id}", ['reason' => 'Retención concluida'])
            ->assertSessionHasNoErrors();
        $this->assertSoftDeleted('player_documents', ['id' => $document->id]);
        Storage::disk('local')->assertMissing($document->getRawOriginal('path'));
    }

    public function test_guardian_document_rejects_files_larger_than_five_megabytes(): void
    {
        [$administrator, , $league, $participation] = $this->scenario();
        $player = $this->player($league, 'Jugador Archivo Grande');
        $player->update(['birth_date' => now()->subYears(14)->toDateString()]);
        $this->registration($player, $participation, 'pending', 23);

        $this->actingAs($administrator)->withSession($this->leagueSession($league, $this->adminRole))
            ->post("/liga/jugadores/{$player->id}/carta-responsiva", [
                'document' => UploadedFile::fake()->create('carta.pdf', 5121, 'application/pdf'),
                'reason' => 'Prueba de límite',
            ])->assertSessionHasErrors('document');
    }

    private function scenario(): array
    {
        $league = League::create(['name' => 'Liga Taladzi', 'slug' => 'liga-taladzi', 'primary_color' => '#125444', 'secondary_color' => '#d9a928', 'status' => 'active']);
        $administrator = User::factory()->create();
        $representative = User::factory()->create();
        $this->membership($league, $administrator, $this->adminRole);
        $representativeMembership = $this->membership($league, $representative, $this->representativeRole);
        $participation = $this->competitionAndTeam($league, $administrator);
        TeamRepresentative::create([
            'team_id' => $participation->team_id, 'league_membership_id' => $representativeMembership->id,
            'user_id' => $representative->id, 'photo_path' => 'private/rep.png', 'ine_path' => 'private/ine.png',
            'status' => 'active', 'started_at' => now(), 'assigned_by' => $administrator->id,
        ]);
        return [$administrator, $representative, $league, $participation];
    }

    private function competitionAndTeam(League $league, User $creator): TeamParticipation
    {
        $season = Season::create(['league_id' => $league->id, 'name' => 'Temporada 2027', 'starts_on' => '2027-01-01', 'ends_on' => '2027-12-31', 'status' => 'active', 'created_by' => $creator->id]);
        $tournament = Tournament::create(['season_id' => $season->id, 'name' => 'Liga', 'slug' => 'liga', 'status' => 'active', 'created_by' => $creator->id]);
        $division = Division::create(['league_id' => $league->id, 'name' => 'Primera', 'slug' => 'primera']);
        $category = Category::create(['league_id' => $league->id, 'name' => 'Libre', 'slug' => 'libre', 'gender' => 'mixed']);
        $regulation = Regulation::create(['league_id' => $league->id, 'name' => 'Reglamento', 'version' => 1, 'status' => 'published', 'league_settings_snapshot' => [], 'created_by' => $creator->id]);
        $competition = Competition::create(['tournament_id' => $tournament->id, 'division_id' => $division->id, 'category_id' => $category->id, 'regulation_id' => $regulation->id, 'name' => 'Primera Libre', 'format' => 'round_robin', 'regular_leg_count' => 1, 'knockout_leg_count' => 2, 'minimum_roster_size' => 1, 'maximum_roster_size' => 30, 'status' => 'active', 'created_by' => $creator->id]);
        $team = Team::create(['league_id' => $league->id, 'name' => 'Equipo Local', 'short_name' => 'Local', 'slug' => 'equipo-local', 'status' => 'active']);
        return TeamParticipation::create(['team_id' => $team->id, 'competition_id' => $competition->id, 'season_id' => $season->id, 'division_id' => $division->id, 'registered_name' => $team->name, 'status' => 'active', 'requested_at' => now(), 'requested_by' => $creator->id]);
    }

    private function secondParticipation(League $league, User $creator, Season $season): TeamParticipation
    {
        $base = TeamParticipation::firstOrFail();
        $team = Team::create(['league_id' => $league->id, 'name' => 'Equipo Visitante', 'short_name' => 'Visitante', 'slug' => 'equipo-visitante', 'status' => 'active']);
        return TeamParticipation::create(['team_id' => $team->id, 'competition_id' => $base->competition_id, 'season_id' => $season->id, 'division_id' => $base->division_id, 'registered_name' => $team->name, 'status' => 'active', 'requested_at' => now(), 'requested_by' => $creator->id]);
    }

    private function playerPayload(TeamParticipation $participation, string $name, int $jersey, string $birthDate = '2000-01-01'): array
    {
        return [
            'full_name' => $name, 'birth_date' => $birthDate, 'gender' => 'unspecified', 'position' => 'midfielder',
            'phone' => '9510000000', 'email' => Str::slug($name).'@example.com',
            'emergency_contact_name' => 'Contacto Familiar', 'emergency_contact_phone' => '9511111111',
            'emergency_contact_relationship' => 'Familiar', 'photo' => $this->fakeImage('jugador.png'),
            'team_participation_id' => $participation->id, 'jersey_number' => $jersey, 'reason' => 'Alta de plantilla',
        ];
    }

    private function player(League $league, string $name, ?int $userId = null): Player
    {
        return Player::create(['league_id' => $league->id, 'user_id' => $userId, 'full_name' => $name, 'birth_date' => '2000-01-01', 'gender' => 'unspecified', 'position' => 'midfielder', 'emergency_contact_name' => 'Contacto', 'emergency_contact_phone' => '9510000000', 'emergency_contact_relationship' => 'Familiar', 'photo_path' => 'players/photos/test.png', 'photo_hash' => hash('sha256', $name), 'status' => 'active']);
    }

    private function registration(Player $player, TeamParticipation $participation, string $status, int $jersey): PlayerRegistration
    {
        $registration = PlayerRegistration::create(['player_id' => $player->id, 'team_id' => $participation->team_id, 'team_participation_id' => $participation->id, 'competition_id' => $participation->competition_id, 'season_id' => $participation->season_id, 'division_id' => $participation->division_id, 'jersey_number' => $jersey, 'status' => $status, 'requested_at' => now()]);
        if ($status === 'active' && $player->status->value === 'active') {
            $sequence = PlayerCredential::count() + 1;
            PlayerCredential::create(['league_id' => $player->league_id, 'player_registration_id' => $registration->id, 'sequence' => $sequence, 'folio' => sprintf('L%04d-2027-%06d', $player->league_id, $sequence), 'status' => 'active', 'snapshot' => [], 'issued_at' => now()]);
        }
        return $registration;
    }

    private function membership(League $league, User $user, Role $role): LeagueMembership
    {
        return LeagueMembership::create(['league_id' => $league->id, 'user_id' => $user->id, 'role_id' => $role->id, 'status' => 'active', 'started_at' => now()]);
    }

    private function leagueSession(League $league, Role $role): array
    {
        return [LeagueContext::LEAGUE_KEY => $league->id, LeagueContext::ROLE_KEY => $role->id];
    }

    private function fakeImage(string $name): UploadedFile
    {
        $png = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNk+A8AAQUBAScY42YAAAAASUVORK5CYII=', true);
        return UploadedFile::fake()->createWithContent($name, $png);
    }
}
