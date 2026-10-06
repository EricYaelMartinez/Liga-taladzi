<?php

namespace Tests\Feature\League;

use App\Domain\Identity\Models\Role;
use App\Domain\Identity\Models\User;
use App\Domain\League\Models\League;
use App\Domain\League\Models\LeagueMembership;
use App\Domain\Referee\Models\Referee;
use App\Domain\Referee\Services\RefereeAvailabilityService;
use App\Support\LeagueContext;
use Carbon\Carbon;
use Database\Seeders\IdentitySeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RefereeManagementTest extends TestCase
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

    public function test_administrator_registers_referee_from_active_referee_membership(): void
    {
        [$administrator, $league] = $this->administrator();
        $user = User::factory()->create(['name' => 'Árbitra Central']);
        $this->membership($league, $user, $this->refereeRole);

        $this->actingAs($administrator)->withSession($this->leagueSession($league, $this->adminRole))
            ->post('/liga/arbitros', $this->refereePayload($user))
            ->assertSessionHasNoErrors();

        $this->assertDatabaseHas('referees', ['league_id' => $league->id, 'user_id' => $user->id, 'category_level' => 'Categoría estatal']);
        $this->assertDatabaseHas('audit_logs', ['action' => 'referee.created']);
    }

    public function test_user_without_active_referee_membership_cannot_be_registered(): void
    {
        [$administrator, $league] = $this->administrator();
        $user = User::factory()->create();

        $this->actingAs($administrator)->withSession($this->leagueSession($league, $this->adminRole))
            ->post('/liga/arbitros', $this->refereePayload($user))
            ->assertSessionHasErrors('user_id');

        $this->assertDatabaseCount('referees', 0);
    }

    public function test_recurring_availability_cannot_overlap_for_same_validity_period(): void
    {
        [$administrator, $league] = $this->administrator();
        $referee = $this->referee($league);
        $session = $this->leagueSession($league, $this->adminRole);
        $first = ['weekday' => 6, 'starts_at' => '08:00', 'ends_at' => '12:00', 'valid_from' => '2027-01-01', 'valid_until' => '2027-06-30', 'reason' => 'Horario sabatino'];

        $this->actingAs($administrator)->withSession($session)->post("/liga/arbitros/{$referee->id}/disponibilidades", $first)->assertSessionHasNoErrors();
        $this->actingAs($administrator)->withSession($session)->post("/liga/arbitros/{$referee->id}/disponibilidades", [
            ...$first, 'starts_at' => '11:00', 'ends_at' => '14:00',
        ])->assertSessionHasErrors('starts_at');

        $this->assertSame(1, $referee->availabilities()->count());
    }

    public function test_referee_can_manage_own_contact_and_availability_but_not_another_profile(): void
    {
        $league = $this->league('Liga Taladzi');
        $own = $this->referee($league, 'Árbitro Propio');
        $other = $this->referee($league, 'Otro Árbitro');
        $session = $this->leagueSession($league, $this->refereeRole);

        $this->actingAs($own->user)->withSession($session)->post("/liga/arbitros/{$own->id}/mi-perfil", [
            'contact_email' => 'arbitro@contacto.test', 'contact_phone' => '9510000000', 'reason' => 'Actualización personal',
        ])->assertSessionHasNoErrors();
        $this->assertDatabaseHas('referees', ['id' => $own->id, 'contact_email' => 'arbitro@contacto.test']);

        $availability = ['weekday' => 7, 'starts_at' => '09:00', 'ends_at' => '15:00', 'valid_from' => null, 'valid_until' => null, 'reason' => 'Disponibilidad dominical'];
        $this->actingAs($own->user)->withSession($session)->post("/liga/arbitros/{$own->id}/disponibilidades", $availability)->assertSessionHasNoErrors();
        $this->actingAs($own->user)->withSession($session)->post("/liga/arbitros/{$other->id}/disponibilidades", $availability)->assertForbidden();
    }

    public function test_internal_notes_and_observations_are_not_exposed_to_referee(): void
    {
        [$administrator, $league] = $this->administrator();
        $referee = $this->referee($league);
        $referee->update(['notes' => 'NOTA-PRIVADA-EXPEDIENTE']);
        $referee->observations()->create(['observed_on' => '2027-01-10', 'observation' => 'OBSERVACION-PRIVADA', 'created_by' => $administrator->id]);

        $this->actingAs($referee->user)->withSession($this->leagueSession($league, $this->refereeRole))
            ->get('/liga/arbitros')
            ->assertOk()
            ->assertDontSee('NOTA-PRIVADA-EXPEDIENTE')
            ->assertDontSee('OBSERVACION-PRIVADA');
    }

    public function test_only_administrator_can_create_internal_observation(): void
    {
        [$administrator, $league] = $this->administrator();
        $referee = $this->referee($league);
        $payload = ['observed_on' => '2027-02-01', 'observation' => 'Buen control del encuentro.', 'reason' => 'Seguimiento arbitral'];

        $this->actingAs($referee->user)->withSession($this->leagueSession($league, $this->refereeRole))
            ->post("/liga/arbitros/{$referee->id}/observaciones", $payload)->assertForbidden();
        $this->actingAs($administrator)->withSession($this->leagueSession($league, $this->adminRole))
            ->post("/liga/arbitros/{$referee->id}/observaciones", $payload)->assertSessionHasNoErrors();
        $this->assertDatabaseHas('referee_observations', ['referee_id' => $referee->id, 'observation' => 'Buen control del encuentro.']);
    }

    public function test_availability_service_checks_status_day_time_and_validity(): void
    {
        $league = $this->league('Liga Taladzi');
        $referee = $this->referee($league);
        $referee->availabilities()->create(['weekday' => 1, 'starts_at' => '08:00', 'ends_at' => '12:00', 'valid_from' => '2027-01-01', 'valid_until' => '2027-06-30']);
        $service = app(RefereeAvailabilityService::class);

        $this->assertTrue($service->isAvailable($referee, Carbon::parse('2027-01-04 09:00'), Carbon::parse('2027-01-04 11:00')));
        $this->assertFalse($service->isAvailable($referee, Carbon::parse('2027-01-04 11:00'), Carbon::parse('2027-01-04 13:00')));
        $this->assertFalse($service->isAvailable($referee, Carbon::parse('2027-07-05 09:00'), Carbon::parse('2027-07-05 11:00')));
        $referee->update(['status' => 'inactive']);
        $this->assertFalse($service->isAvailable($referee->fresh(), Carbon::parse('2027-01-04 09:00'), Carbon::parse('2027-01-04 11:00')));
    }

    public function test_referee_from_another_league_cannot_be_modified(): void
    {
        [$administrator, $league] = $this->administrator();
        $foreign = $this->referee($this->league('Otra Liga'));

        $this->actingAs($administrator)->withSession($this->leagueSession($league, $this->adminRole))
            ->post("/liga/arbitros/{$foreign->id}/actualizar", $this->refereePayload($foreign->user))
            ->assertNotFound();
    }

    private function administrator(): array
    {
        $user = User::factory()->create();
        $league = $this->league('Liga Taladzi');
        $this->membership($league, $user, $this->adminRole);
        return [$user, $league];
    }

    private function referee(League $league, string $name = 'Árbitro Central'): Referee
    {
        $user = User::factory()->create(['name' => $name]);
        $this->membership($league, $user, $this->refereeRole);
        return Referee::create(['league_id' => $league->id, 'user_id' => $user->id, 'category_level' => 'Categoría estatal', 'status' => 'active']);
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

    private function refereePayload(User $user): array
    {
        return ['user_id' => $user->id, 'contact_email' => $user->email, 'contact_phone' => '9511234567', 'category_level' => 'Categoría estatal', 'status' => 'active', 'joined_on' => '2026-10-06', 'notes' => 'Disponible para finales', 'reason' => 'Alta del cuerpo arbitral'];
    }
}
