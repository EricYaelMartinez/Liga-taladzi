<?php

namespace Tests\Feature\League;

use App\Domain\Competition\Models\Division;
use App\Domain\Identity\Models\Role;
use App\Domain\Identity\Models\User;
use App\Domain\League\Models\League;
use App\Domain\League\Models\LeagueMembership;
use App\Domain\League\Models\LeagueSetting;
use App\Domain\Scheduling\Models\PlayingField;
use App\Domain\Scheduling\Models\Venue;
use App\Domain\Scheduling\Services\FieldAvailabilityService;
use App\Support\LeagueContext;
use Carbon\Carbon;
use Database\Seeders\IdentitySeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FieldManagementTest extends TestCase
{
    use RefreshDatabase;

    private Role $adminRole;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(IdentitySeeder::class);
        $this->adminRole = Role::where('slug', 'league_admin')->firstOrFail();
    }

    public function test_administrator_registers_installation_field_and_recommended_divisions(): void
    {
        [$administrator, $league] = $this->administrator();
        $division = Division::create(['league_id' => $league->id, 'name' => 'Primera', 'slug' => 'primera']);
        $session = $this->leagueSession($league, $this->adminRole);

        $this->actingAs($administrator)->withSession($session)->post('/liga/instalaciones', $this->venuePayload())->assertSessionHasNoErrors();
        $venue = Venue::firstOrFail();
        $this->actingAs($administrator)->withSession($session)->post("/liga/instalaciones/{$venue->id}/canchas", $this->fieldPayload([$division->id]))->assertSessionHasNoErrors();

        $field = PlayingField::firstOrFail();
        $this->assertSame('Cancha 1', $field->name);
        $this->assertDatabaseHas('field_division', ['playing_field_id' => $field->id, 'division_id' => $division->id]);
        $this->assertDatabaseHas('audit_logs', ['action' => 'field.created']);
    }

    public function test_duplicate_installation_name_is_rejected_case_insensitively(): void
    {
        [$administrator, $league] = $this->administrator();
        $session = $this->leagueSession($league, $this->adminRole);
        $this->actingAs($administrator)->withSession($session)->post('/liga/instalaciones', $this->venuePayload());
        $payload = $this->venuePayload();
        $payload['name'] = 'UNIDAD DEPORTIVA TALADZI';

        $this->actingAs($administrator)->withSession($session)->post('/liga/instalaciones', $payload)->assertSessionHasErrors('name');
        $this->assertSame(1, Venue::count());
    }

    public function test_recurring_availability_cannot_overlap_on_the_same_date_range(): void
    {
        [$administrator, $league] = $this->administrator();
        $field = $this->field($league);
        $session = $this->leagueSession($league, $this->adminRole);
        $first = ['weekday' => 6, 'starts_at' => '08:00', 'ends_at' => '12:00', 'valid_from' => '2027-01-01', 'valid_until' => '2027-06-30', 'reason' => 'Horario sabatino'];
        $this->actingAs($administrator)->withSession($session)->post("/liga/canchas/{$field->id}/disponibilidades", $first)->assertSessionHasNoErrors();

        $this->actingAs($administrator)->withSession($session)->post("/liga/canchas/{$field->id}/disponibilidades", [
            ...$first, 'starts_at' => '11:00', 'ends_at' => '14:00', 'valid_from' => '2027-04-01',
        ])->assertSessionHasErrors('starts_at');
        $this->assertSame(1, $field->availabilities()->count());
    }

    public function test_same_time_is_allowed_when_validity_ranges_do_not_overlap(): void
    {
        [$administrator, $league] = $this->administrator();
        $field = $this->field($league);
        $session = $this->leagueSession($league, $this->adminRole);
        foreach ([['2027-01-01', '2027-06-30'], ['2027-07-01', '2027-12-31']] as [$from, $until]) {
            $this->actingAs($administrator)->withSession($session)->post("/liga/canchas/{$field->id}/disponibilidades", [
                'weekday' => 6, 'starts_at' => '08:00', 'ends_at' => '12:00', 'valid_from' => $from, 'valid_until' => $until, 'reason' => 'Horario por semestre',
            ])->assertSessionHasNoErrors();
        }
        $this->assertSame(2, $field->availabilities()->count());
    }

    public function test_field_availability_service_applies_buffer_and_blocks(): void
    {
        [$administrator, $league] = $this->administrator();
        LeagueSetting::create(['league_id' => $league->id, 'match_periods' => 2, 'schedule_buffer_minutes' => 15, 'appeal_deadline_hours' => 2, 'reactivation_window_days' => 21, 'currency' => 'MXN']);
        $field = $this->field($league);
        $field->availabilities()->create(['weekday' => 1, 'starts_at' => '08:00', 'ends_at' => '10:00']);
        $service = app(FieldAvailabilityService::class);

        $this->assertTrue($service->isAvailable($field, Carbon::parse('2027-01-04 08:00'), Carbon::parse('2027-01-04 09:45')));
        $this->assertFalse($service->isAvailable($field, Carbon::parse('2027-01-04 08:00'), Carbon::parse('2027-01-04 09:50')));

        $field->blocks()->create(['type' => 'maintenance', 'starts_at' => '2027-01-04 09:00', 'ends_at' => '2027-01-04 11:00', 'reason' => 'Riego', 'created_by' => $administrator->id]);
        $this->assertFalse($service->isAvailable($field, Carbon::parse('2027-01-04 08:30'), Carbon::parse('2027-01-04 09:30')));
    }

    public function test_player_cannot_open_field_management(): void
    {
        $league = $this->league('Liga Taladzi');
        $player = User::factory()->create();
        $playerRole = Role::where('slug', 'player')->firstOrFail();
        $this->membership($league, $player, $playerRole);

        $this->actingAs($player)->withSession($this->leagueSession($league, $playerRole))->get('/liga/campos')->assertForbidden();
    }

    public function test_field_from_another_league_cannot_be_modified(): void
    {
        [$administrator, $league] = $this->administrator();
        $foreign = $this->field($this->league('Otra Liga'));

        $this->actingAs($administrator)->withSession($this->leagueSession($league, $this->adminRole))
            ->put("/liga/canchas/{$foreign->id}", $this->fieldPayload())
            ->assertNotFound();
    }

    private function administrator(): array
    {
        $user = User::factory()->create();
        $league = $this->league('Liga Taladzi');
        $this->membership($league, $user, $this->adminRole);
        return [$user, $league];
    }

    private function league(string $name): League
    {
        return League::create(['name' => $name, 'slug' => str($name)->slug()->toString(), 'primary_color' => '#125444', 'secondary_color' => '#d9a928', 'status' => 'active']);
    }

    private function field(League $league): PlayingField
    {
        $venue = Venue::create(['league_id' => $league->id, 'name' => 'Unidad '.uniqid(), 'address' => 'Centro', 'status' => 'active']);
        return PlayingField::create(['venue_id' => $venue->id, 'name' => 'Cancha 1', 'surface' => 'synthetic', 'has_lighting' => true, 'status' => 'active']);
    }

    private function membership(League $league, User $user, Role $role): LeagueMembership
    {
        return LeagueMembership::create(['league_id' => $league->id, 'user_id' => $user->id, 'role_id' => $role->id, 'status' => 'active', 'started_at' => now()]);
    }

    private function leagueSession(League $league, Role $role): array
    {
        return [LeagueContext::LEAGUE_KEY => $league->id, LeagueContext::ROLE_KEY => $role->id];
    }

    private function venuePayload(): array
    {
        return ['name' => 'Unidad Deportiva Taladzi', 'address' => 'Calle Principal 100', 'latitude' => '17.0600000', 'longitude' => '-96.7200000', 'contact_name' => 'Encargado', 'phone' => '9510000000', 'notes' => 'Acceso por la entrada norte', 'status' => 'active', 'reason' => 'Alta de instalación'];
    }

    private function fieldPayload(array $divisions = []): array
    {
        return ['name' => 'Cancha 1', 'surface' => 'synthetic', 'has_lighting' => true, 'capacity' => 500, 'notes' => 'Cancha reglamentaria', 'status' => 'active', 'division_ids' => $divisions, 'reason' => 'Alta de cancha'];
    }
}
