<?php

namespace Tests\Feature\League;

use App\Domain\Competition\Models\Category;
use App\Domain\Competition\Models\Competition;
use App\Domain\Competition\Models\Division;
use App\Domain\Competition\Models\Regulation;
use App\Domain\Competition\Models\Season;
use App\Domain\Competition\Models\Tournament;
use App\Domain\Finance\Models\FinanceCharge;
use App\Domain\Finance\Services\FinanceService;
use App\Domain\Identity\Models\User;
use App\Domain\League\Models\League;
use App\Domain\League\Models\LeagueSetting;
use App\Domain\Team\Models\Team;
use App\Domain\Team\Models\TeamParticipation;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class FinanceManagementTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_cash_payment_can_be_split_between_multiple_charges_and_preserves_a_partial_balance(): void
    {
        $scenario = $this->scenario();
        $service = app(FinanceService::class);
        $first = $this->charge($service, $scenario, 'Inscripción', 100);
        $second = $this->charge($service, $scenario, 'Arbitraje', 80);

        $payment = $service->registerPayment([
            'team_id' => $scenario['team']->id,
            'paid_at' => now(),
            'notes' => 'Pago en caja',
            'allocations' => [
                ['charge_id' => $first->id, 'amount' => 50],
                ['charge_id' => $second->id, 'amount' => 80],
            ],
        ], $scenario['league']->id, $scenario['user']->id);

        $this->assertSame('REC-000001', $payment->receipt_number);
        $this->assertSame('130.00', $payment->amount);
        $this->assertSame('partial', $first->fresh()->status);
        $this->assertSame('50.00', $first->fresh()->balance);
        $this->assertSame('paid', $second->fresh()->status);
        $this->assertSame('0.00', $second->fresh()->balance);
        $this->assertDatabaseCount('finance_payment_allocations', 2);
    }

    public function test_grace_suspension_reactivation_and_final_exclusion_follow_league_settings(): void
    {
        Carbon::setTestNow('2027-01-01 10:00:00');
        $scenario = $this->scenario(7, 21);
        $service = app(FinanceService::class);
        $charge = $this->charge($service, $scenario, 'Cuota enero', 100, '2027-01-01');

        Carbon::setTestNow('2027-01-05 10:00:00');
        $service->evaluateLeague($scenario['league']->id, $scenario['user']->id);
        $this->assertSame('grace', $scenario['participation']->fresh()->financial_status);
        $this->assertSame('active', $scenario['participation']->fresh()->status->value);

        Carbon::setTestNow('2027-01-09 10:00:00');
        $service->evaluateLeague($scenario['league']->id, $scenario['user']->id);
        $this->assertSame('suspended', $scenario['participation']->fresh()->financial_status);
        $this->assertSame('suspended', $scenario['participation']->fresh()->status->value);

        $service->registerPayment(['team_id' => $scenario['team']->id, 'paid_at' => now(), 'allocations' => [['charge_id' => $charge->id, 'amount' => 100]]], $scenario['league']->id, $scenario['user']->id);
        $this->assertSame('clear', $scenario['participation']->fresh()->financial_status);
        $this->assertSame('active', $scenario['participation']->fresh()->status->value);

        Carbon::setTestNow('2027-01-01 10:00:00');
        $this->charge($service, $scenario, 'Cuota extraordinaria', 50, '2027-01-01');
        Carbon::setTestNow('2027-01-30 10:00:00');
        $service->evaluateLeague($scenario['league']->id, $scenario['user']->id);
        $this->assertSame('excluded', $scenario['participation']->fresh()->financial_status);
        $this->assertSame('deregistered', $scenario['participation']->fresh()->status->value);
    }

    public function test_bond_synchronization_is_idempotent(): void
    {
        $scenario = $this->scenario();
        LeagueSetting::where('league_id', $scenario['league']->id)->update(['bond_enabled' => true, 'bond_amount' => 750]);
        $service = app(FinanceService::class);

        $this->assertCount(1, $service->syncObligations($scenario['league']->id, $scenario['user']->id));
        $this->assertCount(0, $service->syncObligations($scenario['league']->id, $scenario['user']->id));
        $this->assertDatabaseHas('finance_charges', ['league_id' => $scenario['league']->id, 'team_participation_id' => $scenario['participation']->id, 'type' => 'bond', 'original_amount' => 750]);
    }

    public function test_bulk_charge_creates_one_independent_debt_per_participation(): void
    {
        $scenario = $this->scenario();
        $secondTeam = Team::create(['league_id' => $scenario['league']->id, 'name' => 'Halcones', 'short_name' => 'Halcones', 'slug' => 'halcones-'.uniqid(), 'status' => 'active']);
        $secondParticipation = TeamParticipation::create([
            'team_id' => $secondTeam->id,
            'competition_id' => $scenario['competition']->id,
            'season_id' => $scenario['season']->id,
            'division_id' => $scenario['division']->id,
            'registered_name' => 'Halcones',
            'status' => 'active',
            'requested_at' => now(),
        ]);

        $charges = app(FinanceService::class)->createBulkCharges([
            'team_participation_ids' => [$scenario['participation']->id, $secondParticipation->id],
            'concept_id' => null,
            'description' => 'Cuota de arbitraje',
            'type' => 'fee',
            'amount' => 350,
            'issued_on' => '2027-02-01',
            'due_on' => '2027-02-10',
        ], collect([$scenario['participation'], $secondParticipation]), $scenario['league']->id, $scenario['user']->id);

        $this->assertCount(2, $charges);
        $this->assertDatabaseHas('finance_charges', ['team_id' => $scenario['team']->id, 'team_participation_id' => $scenario['participation']->id, 'original_amount' => 350, 'balance' => 350]);
        $this->assertDatabaseHas('finance_charges', ['team_id' => $secondTeam->id, 'team_participation_id' => $secondParticipation->id, 'original_amount' => 350, 'balance' => 350]);
        $this->assertNotSame($charges[0]->source_key, $charges[1]->source_key);
        $this->assertSame(str($charges[0]->source_key)->beforeLast(':')->toString(), str($charges[1]->source_key)->beforeLast(':')->toString());
    }

    public function test_bulk_charge_rejects_duplicates_without_creating_a_partial_batch(): void
    {
        $scenario = $this->scenario();
        $service = app(FinanceService::class);
        $data = [
            'team_participation_ids' => [$scenario['participation']->id],
            'concept_id' => null,
            'description' => 'Cuota mensual',
            'type' => 'fee',
            'amount' => 500,
            'issued_on' => '2027-03-01',
            'due_on' => '2027-03-10',
        ];
        $service->createBulkCharges($data, collect([$scenario['participation']]), $scenario['league']->id, $scenario['user']->id);

        $secondTeam = Team::create(['league_id' => $scenario['league']->id, 'name' => 'Leones', 'short_name' => 'Leones', 'slug' => 'leones-'.uniqid(), 'status' => 'active']);
        $secondParticipation = TeamParticipation::create([
            'team_id' => $secondTeam->id,
            'competition_id' => $scenario['competition']->id,
            'season_id' => $scenario['season']->id,
            'division_id' => $scenario['division']->id,
            'registered_name' => 'Leones',
            'status' => 'active',
            'requested_at' => now(),
        ]);

        try {
            $service->createBulkCharges(
                [...$data, 'team_participation_ids' => [$scenario['participation']->id, $secondParticipation->id]],
                collect([$scenario['participation'], $secondParticipation]),
                $scenario['league']->id,
                $scenario['user']->id,
            );
            $this->fail('La operación debió rechazar el adeudo duplicado.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('team_participation_ids', $exception->errors());
        }

        $this->assertDatabaseMissing('finance_charges', ['team_participation_id' => $secondParticipation->id, 'description' => 'Cuota mensual']);
        $this->assertDatabaseCount('finance_charges', 1);
    }

    private function charge(FinanceService $service, array $scenario, string $description, float $amount, string $dueOn = '2027-12-01'): FinanceCharge
    {
        return $service->createCharge([
            'team_id' => $scenario['team']->id,
            'team_participation_id' => $scenario['participation']->id,
            'season_id' => $scenario['season']->id,
            'concept_id' => null,
            'description' => $description,
            'type' => 'fee',
            'amount' => $amount,
            'issued_on' => '2027-01-01',
            'due_on' => $dueOn,
        ], $scenario['league']->id, $scenario['user']->id);
    }

    private function scenario(?int $graceDays = 7, int $reactivationDays = 21): array
    {
        $user = User::factory()->create();
        $league = League::create(['name' => 'Liga Financiera', 'slug' => 'liga-financiera-'.uniqid(), 'primary_color' => '#125444', 'secondary_color' => '#d9a928', 'status' => 'active']);
        LeagueSetting::create(['league_id' => $league->id, 'match_periods' => 2, 'appeal_deadline_hours' => 2, 'payment_grace_days' => $graceDays, 'reactivation_window_days' => $reactivationDays, 'bond_enabled' => false, 'currency' => 'MXN']);
        $season = Season::create(['league_id' => $league->id, 'name' => '2027', 'starts_on' => '2027-01-01', 'ends_on' => '2027-12-31', 'status' => 'active']);
        $tournament = Tournament::create(['season_id' => $season->id, 'name' => 'Liga', 'slug' => 'liga-'.uniqid(), 'status' => 'active']);
        $division = Division::create(['league_id' => $league->id, 'name' => 'Primera', 'slug' => 'primera-'.uniqid(), 'status' => 'active']);
        $category = Category::create(['league_id' => $league->id, 'name' => 'Libre', 'slug' => 'libre-'.uniqid(), 'status' => 'active']);
        $regulation = Regulation::create(['league_id' => $league->id, 'name' => 'General', 'version' => 1, 'status' => 'in_use', 'points_win' => 3, 'points_draw' => 1, 'points_loss' => 0, 'fair_play_yellow_points' => 1, 'fair_play_second_yellow_points' => 2, 'fair_play_red_points' => 3, 'league_settings_snapshot' => []]);
        $competition = Competition::create(['tournament_id' => $tournament->id, 'division_id' => $division->id, 'category_id' => $category->id, 'regulation_id' => $regulation->id, 'name' => 'Primera Libre', 'format' => 'round_robin', 'regular_leg_count' => 1, 'knockout_leg_count' => 2, 'minimum_roster_size' => 1, 'maximum_roster_size' => 30, 'status' => 'active']);
        $team = Team::create(['league_id' => $league->id, 'name' => 'Águilas', 'short_name' => 'Águilas', 'slug' => 'aguilas-'.uniqid(), 'status' => 'active']);
        $participation = TeamParticipation::create(['team_id' => $team->id, 'competition_id' => $competition->id, 'season_id' => $season->id, 'division_id' => $division->id, 'registered_name' => 'Águilas', 'status' => 'active', 'requested_at' => now()]);
        return compact('user', 'league', 'season', 'competition', 'division', 'team', 'participation');
    }
}
