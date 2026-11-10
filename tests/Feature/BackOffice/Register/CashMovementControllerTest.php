<?php

namespace Tests\Feature\BackOffice\Register;

use App\Models\CashMovement;
use App\Models\OperatingDay;
use App\Models\Season;
use App\Models\User;
use Carbon\Carbon;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CashMovementControllerTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);
        $this->admin = User::factory()->create();
        $this->admin->assignRole('admin');
        $this->actingAs($this->admin);
    }

    private function createSeason(float $initialBalance = 1000): Season
    {
        return Season::factory()->create([
            'start_date' => Carbon::parse('2026-05-01'),
            'end_date' => Carbon::parse('2026-06-30'),
            'initial_balance' => $initialBalance,
        ]);
    }

    private function createClosedDay(Season $season, string $date, float $totalCash, float $closingBalance): OperatingDay
    {
        return OperatingDay::factory()->closed()->create([
            'season_id' => $season->id,
            'date' => Carbon::parse($date),
            'total_cash' => $totalCash,
            'closing_balance' => $closingBalance,
        ]);
    }

    public function test_injection_is_counted_from_the_first_day_on_or_after_its_date(): void
    {
        $season = $this->createSeason();
        $day1 = $this->createClosedDay($season, '2026-05-01', 200, 1200);
        $day2 = $this->createClosedDay($season, '2026-05-02', 100, 1300);

        $this->post(route('bo.register.seasons.cash-movements.store', $season), [
            'direction' => 'in',
            'amount' => 500,
            'occurred_at' => '2026-05-02',
            'remark' => 'Apport de la banque',
        ])->assertRedirect()->assertSessionHas('success');

        $this->assertDatabaseHas('cash_movements', ['season_id' => $season->id, 'amount' => 500]);
        $this->assertEquals(1200.00, $day1->fresh()->closing_balance);
        $this->assertEquals(1800.00, $day2->fresh()->closing_balance);
        $this->assertEquals(1800.00, $season->fresh()->end_balance);
    }

    public function test_withdrawal_is_counted_from_the_first_day_on_or_after_its_date(): void
    {
        $season = $this->createSeason();
        $day1 = $this->createClosedDay($season, '2026-05-01', 200, 1200);
        $day2 = $this->createClosedDay($season, '2026-05-02', 100, 1300);

        $this->post(route('bo.register.seasons.cash-movements.store', $season), [
            'direction' => 'out',
            'amount' => 300,
            'occurred_at' => '2026-05-02',
        ])->assertRedirect();

        $this->assertDatabaseHas('cash_movements', ['season_id' => $season->id, 'amount' => -300]);
        $this->assertEquals(1200.00, $day1->fresh()->closing_balance);
        $this->assertEquals(1000.00, $day2->fresh()->closing_balance);
        $this->assertEquals(1000.00, $season->fresh()->end_balance);
    }

    public function test_movement_dated_before_first_day_counts_in_first_day(): void
    {
        $season = $this->createSeason();
        $day1 = $this->createClosedDay($season, '2026-05-10', 200, 1200);

        $this->post(route('bo.register.seasons.cash-movements.store', $season), [
            'direction' => 'in',
            'amount' => 100,
            'occurred_at' => '2026-05-05',
        ])->assertRedirect();

        $this->assertEquals(1300.00, $day1->fresh()->closing_balance);
    }

    public function test_movement_after_last_day_only_affects_end_balance(): void
    {
        $season = $this->createSeason();
        $day1 = $this->createClosedDay($season, '2026-05-01', 200, 1200);

        $this->post(route('bo.register.seasons.cash-movements.store', $season), [
            'direction' => 'out',
            'amount' => 800,
            'occurred_at' => '2026-06-20',
            'remark' => 'Dépôt en banque de fin de saison',
        ])->assertRedirect();

        $this->assertEquals(1200.00, $day1->fresh()->closing_balance);
        $this->assertEquals(400.00, $season->fresh()->end_balance);
    }

    public function test_balance_summary_reconciles_last_day_and_post_season_movements(): void
    {
        $season = $this->createSeason();
        $this->createClosedDay($season, '2026-05-01', 200, 1200);
        $this->createClosedDay($season, '2026-05-02', 100, 1300);

        CashMovement::factory()->injection()->create([
            'season_id' => $season->id,
            'amount' => 500,
            'occurred_at' => '2026-05-02',
        ]);
        CashMovement::factory()->withdrawal()->create([
            'season_id' => $season->id,
            'amount' => -200,
            'occurred_at' => '2026-06-20',
        ]);

        $summary = $season->fresh()->balanceSummary();

        $this->assertEquals(1000.00, $summary['initial_balance']);
        $this->assertEquals(300.00, $summary['sales_total']);
        $this->assertEquals(300.00, $summary['movements_total']);
        $this->assertEquals(1800.00, $summary['last_day_balance']);
        $this->assertEquals(-200.00, $summary['post_last_day_movements']);
        $this->assertEquals(1600.00, $summary['end_balance']);
    }

    public function test_update_recomputes_balances_and_keeps_earlier_days_unchanged(): void
    {
        $season = $this->createSeason();
        $day1 = $this->createClosedDay($season, '2026-05-01', 200, 1200);
        $day2 = $this->createClosedDay($season, '2026-05-02', 100, 1300);

        $movement = CashMovement::factory()->injection()->create([
            'season_id' => $season->id,
            'amount' => 500,
            'occurred_at' => '2026-05-02',
        ]);
        $season->recalculateClosingBalances();
        $this->assertEquals(1800.00, $day2->fresh()->closing_balance);

        $this->put(route('bo.register.seasons.cash-movements.update', [$season, $movement]), [
            'direction' => 'in',
            'amount' => 50,
            'occurred_at' => '2026-05-02',
        ])->assertRedirect()->assertSessionHas('success');

        $this->assertEquals(1200.00, $day1->fresh()->closing_balance);
        $this->assertEquals(1350.00, $day2->fresh()->closing_balance);
        $this->assertDatabaseHas('cash_movements', ['id' => $movement->id, 'amount' => 50]);
    }

    public function test_update_can_turn_an_injection_into_a_withdrawal(): void
    {
        $season = $this->createSeason();
        $day1 = $this->createClosedDay($season, '2026-05-01', 200, 1200);

        $movement = CashMovement::factory()->injection()->create([
            'season_id' => $season->id,
            'amount' => 500,
            'occurred_at' => '2026-05-01',
        ]);

        $this->put(route('bo.register.seasons.cash-movements.update', [$season, $movement]), [
            'direction' => 'out',
            'amount' => 100,
            'occurred_at' => '2026-05-01',
        ])->assertRedirect();

        $this->assertDatabaseHas('cash_movements', ['id' => $movement->id, 'amount' => -100]);
        $this->assertEquals(1100.00, $day1->fresh()->closing_balance);
    }

    public function test_destroy_removes_movement_and_recomputes_balances(): void
    {
        $season = $this->createSeason();
        $day1 = $this->createClosedDay($season, '2026-05-01', 200, 1200);

        $movement = CashMovement::factory()->withdrawal()->create([
            'season_id' => $season->id,
            'amount' => -300,
            'occurred_at' => '2026-05-01',
        ]);
        $season->recalculateClosingBalances();
        $this->assertEquals(900.00, $day1->fresh()->closing_balance);

        $this->delete(route('bo.register.seasons.cash-movements.destroy', [$season, $movement]))
            ->assertRedirect()
            ->assertSessionHas('success');

        $this->assertDatabaseMissing('cash_movements', ['id' => $movement->id]);
        $this->assertEquals(1200.00, $day1->fresh()->closing_balance);
        $this->assertEquals(1200.00, $season->fresh()->end_balance);
    }

    public function test_full_cash_lifecycle_with_initial_balance_correction(): void
    {
        $season = $this->createSeason(1000);
        $day1 = $this->createClosedDay($season, '2026-05-01', 200, 1200);

        $this->put(route('bo.register.seasons.update', $season), [
            'initial_balance' => 1500,
        ])->assertRedirect();
        $this->assertEquals(1700.00, $day1->fresh()->closing_balance);

        $this->post(route('bo.register.seasons.cash-movements.store', $season), [
            'direction' => 'in',
            'amount' => 500,
            'occurred_at' => '2026-05-02',
        ])->assertRedirect()->assertSessionHasNoErrors();

        $day2 = $this->createClosedDay($season, '2026-05-02', 0, 0);
        $season->refresh()->recalculateClosingBalances();
        $this->assertEquals(2200.00, $day2->fresh()->closing_balance);

        $this->post(route('bo.register.seasons.cash-movements.store', $season), [
            'direction' => 'out',
            'amount' => 300,
            'occurred_at' => '2026-06-10',
        ])->assertRedirect();

        $season->refresh();
        $this->assertEquals(2200.00, $day2->fresh()->closing_balance);
        $this->assertEquals(1900.00, $season->end_balance);
    }

    public function test_days_without_movements_keep_their_closing_balance(): void
    {
        $season = $this->createSeason();
        $day1 = $this->createClosedDay($season, '2026-05-01', 200, 1200);
        $day2 = $this->createClosedDay($season, '2026-05-02', 100, 1300);

        CashMovement::factory()->injection()->create([
            'season_id' => $season->id,
            'amount' => 500,
            'occurred_at' => '2026-05-03',
        ]);

        $season->recalculateClosingBalances();

        $this->assertEquals(1200.00, $day1->fresh()->closing_balance);
        $this->assertEquals(1300.00, $day2->fresh()->closing_balance);
        $this->assertEquals(1800.00, $season->fresh()->end_balance);
    }

    public function test_store_rejects_zero_and_negative_amounts(): void
    {
        $season = $this->createSeason();

        $this->post(route('bo.register.seasons.cash-movements.store', $season), [
            'direction' => 'in',
            'amount' => 0,
            'occurred_at' => '2026-05-02',
        ])->assertSessionHasErrors(['amount']);

        $this->post(route('bo.register.seasons.cash-movements.store', $season), [
            'direction' => 'in',
            'amount' => -50,
            'occurred_at' => '2026-05-02',
        ])->assertSessionHasErrors(['amount']);

        $this->assertDatabaseCount('cash_movements', 0);
    }

    public function test_store_rejects_invalid_direction(): void
    {
        $season = $this->createSeason();

        $this->post(route('bo.register.seasons.cash-movements.store', $season), [
            'direction' => 'sideways',
            'amount' => 10,
            'occurred_at' => '2026-05-02',
        ])->assertSessionHasErrors(['direction']);
    }

    public function test_store_rejects_dates_outside_the_season(): void
    {
        $season = $this->createSeason();

        $this->post(route('bo.register.seasons.cash-movements.store', $season), [
            'direction' => 'in',
            'amount' => 10,
            'occurred_at' => '2026-04-30',
        ])->assertSessionHasErrors(['occurred_at']);

        $this->post(route('bo.register.seasons.cash-movements.store', $season), [
            'direction' => 'in',
            'amount' => 10,
            'occurred_at' => '2026-07-01',
        ])->assertSessionHasErrors(['occurred_at']);

        $this->assertDatabaseCount('cash_movements', 0);
    }

    public function test_store_rejects_future_occurred_at(): void
    {
        $season = $this->createSeason();

        $this->post(route('bo.register.seasons.cash-movements.store', $season), [
            'direction' => 'in',
            'amount' => 10,
            'occurred_at' => Carbon::tomorrow('Europe/Zurich')->toDateString(),
        ])->assertSessionHasErrors(['occurred_at']);

        $this->assertDatabaseCount('cash_movements', 0);
    }

    public function test_update_rejects_future_occurred_at(): void
    {
        $season = $this->createSeason();
        $movement = CashMovement::factory()->injection()->create([
            'season_id' => $season->id,
            'amount' => 100,
            'occurred_at' => '2026-05-02',
        ]);

        $this->put(route('bo.register.seasons.cash-movements.update', [$season, $movement]), [
            'direction' => 'in',
            'amount' => 10,
            'occurred_at' => Carbon::tomorrow('Europe/Zurich')->toDateString(),
        ])->assertSessionHasErrors(['occurred_at']);

        $this->assertDatabaseHas('cash_movements', ['id' => $movement->id, 'amount' => 100]);
    }

    public function test_store_accepts_today(): void
    {
        $season = Season::factory()->create([
            'start_date' => Carbon::today('Europe/Zurich')->subDays(10),
            'end_date' => Carbon::today('Europe/Zurich')->addDays(10),
            'initial_balance' => 100,
        ]);

        $this->post(route('bo.register.seasons.cash-movements.store', $season), [
            'direction' => 'in',
            'amount' => 10,
            'occurred_at' => Carbon::today('Europe/Zurich')->toDateString(),
        ])->assertSessionHasNoErrors()->assertRedirect();

        $this->assertDatabaseCount('cash_movements', 1);
    }

    public function test_store_rejects_too_long_remark(): void
    {
        $season = $this->createSeason();

        $this->post(route('bo.register.seasons.cash-movements.store', $season), [
            'direction' => 'in',
            'amount' => 10,
            'occurred_at' => '2026-05-02',
            'remark' => str_repeat('a', 256),
        ])->assertSessionHasErrors(['remark']);
    }

    public function test_requires_manage_cash_movements_permission(): void
    {
        $season = $this->createSeason();
        $movement = CashMovement::factory()->injection()->create([
            'season_id' => $season->id,
            'amount' => 100,
            'occurred_at' => '2026-05-02',
        ]);

        $user = User::factory()->create();
        $user->givePermissionTo('view register');
        $this->actingAs($user);

        $payload = ['direction' => 'in', 'amount' => 10, 'occurred_at' => '2026-05-02'];

        $this->post(route('bo.register.seasons.cash-movements.store', $season), $payload)->assertForbidden();
        $this->put(route('bo.register.seasons.cash-movements.update', [$season, $movement]), $payload)->assertForbidden();
        $this->delete(route('bo.register.seasons.cash-movements.destroy', [$season, $movement]))->assertForbidden();

        $this->assertDatabaseHas('cash_movements', ['id' => $movement->id, 'amount' => 100]);
    }

    public function test_cannot_touch_a_movement_from_another_season(): void
    {
        $season = $this->createSeason();
        $otherSeason = $this->createSeason();
        $movement = CashMovement::factory()->injection()->create([
            'season_id' => $otherSeason->id,
            'amount' => 100,
            'occurred_at' => '2026-05-02',
        ]);

        $this->put(route('bo.register.seasons.cash-movements.update', [$season, $movement]), [
            'direction' => 'in',
            'amount' => 10,
            'occurred_at' => '2026-05-02',
        ])->assertNotFound();

        $this->delete(route('bo.register.seasons.cash-movements.destroy', [$season, $movement]))->assertNotFound();

        $this->assertDatabaseHas('cash_movements', ['id' => $movement->id, 'amount' => 100]);
    }

    public function test_season_show_exposes_movements_and_balance_summary(): void
    {
        $season = $this->createSeason();
        $this->createClosedDay($season, '2026-05-01', 200, 1200);
        CashMovement::factory()->injection()->create([
            'season_id' => $season->id,
            'amount' => 100,
            'occurred_at' => '2026-05-01',
        ]);

        $response = $this->get(route('bo.register.seasons.show', $season));

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->where('balanceSummary.end_balance', 1300)
            ->where('season.cash_movements.0.amount', '100.00'));
    }
}
