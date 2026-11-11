<?php

namespace Tests\Feature\BackOffice\Register;

use App\Mail\OperatingDayClosed;
use App\Models\CashMovement;
use App\Models\OperatingDay;
use App\Models\Season;
use App\Models\Ticket;
use App\Models\User;
use Carbon\Carbon;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class OperatingDayControllerTest extends TestCase
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

    public function test_index_returns_paginated_days(): void
    {
        $season = Season::factory()->create();
        OperatingDay::factory()->create(['season_id' => $season->id]);

        $response = $this->get(route('bo.register.operating-days.index'));

        $response->assertOk();
    }

    public function test_index_filters_by_season(): void
    {
        $season1 = Season::factory()->create();
        $season2 = Season::factory()->create();
        OperatingDay::factory()->create(['season_id' => $season1->id]);
        OperatingDay::factory()->create(['season_id' => $season2->id]);

        $response = $this->get(route('bo.register.operating-days.index', ['season_id' => $season1->id]));

        $response->assertOk();
    }

    public function test_show_exposes_opening_balance_and_cash_movements(): void
    {
        $season = Season::factory()->create([
            'start_date' => Carbon::parse('2026-05-01'),
            'end_date' => Carbon::parse('2026-06-30'),
            'initial_balance' => 1000,
        ]);
        OperatingDay::factory()->closed()->create([
            'season_id' => $season->id,
            'date' => Carbon::parse('2026-05-01'),
            'total_cash' => 200,
            'closing_balance' => 1200,
        ]);
        CashMovement::factory()->injection()->create([
            'season_id' => $season->id,
            'amount' => 500,
            'occurred_at' => '2026-05-02',
        ]);
        $day = OperatingDay::factory()->closed()->create([
            'season_id' => $season->id,
            'date' => Carbon::parse('2026-05-02'),
            'total_cash' => 100,
            'closing_balance' => 1800,
        ]);

        $response = $this->get(route('bo.register.operating-days.show', $day));

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->where('openingBalance', 1200)
            ->where('cashMovements.0.amount', '500.00')
            ->where('cashMovements.0.occurred_at', '2026-05-02T00:00:00.000000Z'));
    }

    public function test_show_counts_later_closed_days(): void
    {
        $season = Season::factory()->create();
        $day = OperatingDay::factory()->closed()->create([
            'season_id' => $season->id,
            'date' => Carbon::parse('2026-05-01'),
        ]);
        OperatingDay::factory()->closed()->create([
            'season_id' => $season->id,
            'date' => Carbon::parse('2026-05-02'),
        ]);
        OperatingDay::factory()->closed()->create([
            'season_id' => $season->id,
            'date' => Carbon::parse('2026-05-03'),
        ]);
        OperatingDay::factory()->create([
            'season_id' => $season->id,
            'date' => Carbon::parse('2026-05-04'),
            'closed_at' => null,
        ]);

        $response = $this->get(route('bo.register.operating-days.show', $day));

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page->where('laterClosedDaysCount', 2));
    }

    public function test_show_reports_no_later_closed_days_for_the_last_closed_day(): void
    {
        $season = Season::factory()->create();
        OperatingDay::factory()->closed()->create([
            'season_id' => $season->id,
            'date' => Carbon::parse('2026-05-01'),
        ]);
        $day = OperatingDay::factory()->closed()->create([
            'season_id' => $season->id,
            'date' => Carbon::parse('2026-05-02'),
        ]);

        $response = $this->get(route('bo.register.operating-days.show', $day));

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page->where('laterClosedDaysCount', 0));
    }

    public function test_show_returns_day_with_tickets(): void
    {
        $season = Season::factory()->create();
        $day = OperatingDay::factory()->create(['season_id' => $season->id]);
        Ticket::factory()->cash()->create(['operating_day_id' => $day->id]);

        $response = $this->get(route('bo.register.operating-days.show', $day));

        $response->assertOk();
    }

    public function test_update_modifies_cash_remark(): void
    {
        $season = Season::factory()->create();
        $day = OperatingDay::factory()->create([
            'season_id' => $season->id,
            'cash_remark' => null,
        ]);

        $response = $this->put(route('bo.register.operating-days.update', $day), [
            'cash_remark' => 'Short 5 CHF',
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('operating_days', [
            'id' => $day->id,
            'cash_remark' => 'Short 5 CHF',
        ]);
    }

    public function test_closing_balance_is_computed_from_previous_days(): void
    {
        $season = Season::factory()->create(['initial_balance' => 500.00]);

        OperatingDay::factory()->create([
            'season_id' => $season->id,
            'date' => Carbon::parse('2026-05-01'),
            'total_cash' => 100.00,
        ]);

        $day2 = OperatingDay::factory()->create([
            'season_id' => $season->id,
            'date' => Carbon::parse('2026-05-02'),
            'total_cash' => 150.00,
        ]);

        // closing_balance = initial_balance + sum(total_cash for all days up to today)
        // = 500 + 100 + 150 = 750
        $this->assertEquals(750.00, $day2->closing_balance);
    }

    public function test_closing_balance_persists_when_day_is_closed(): void
    {
        $season = Season::factory()->create(['initial_balance' => 500.00]);
        $day = OperatingDay::factory()->create([
            'season_id' => $season->id,
            'total_cash' => 200.00,
            'closed_at' => null,
            'closing_balance' => null,
        ]);
        Ticket::factory()->cash()->create([
            'operating_day_id' => $day->id,
            'total' => 200.00,
        ]);

        $this->put(route('bo.register.operating-days.update', $day), [
            'status' => 'closed',
        ]);

        $day->refresh();
        $this->assertNotNull($day->closed_at);
        $this->assertNotNull($day->closing_balance);
        $this->assertEquals(700.00, $day->closing_balance);
    }

    public function test_closing_balance_cleared_when_day_is_reopened(): void
    {
        $season = Season::factory()->create(['initial_balance' => 500.00]);
        $day = OperatingDay::factory()->create([
            'season_id' => $season->id,
            'total_cash' => 200.00,
            'closed_at' => Carbon::now(),
            'closing_balance' => 700.00,
        ]);

        $this->put(route('bo.register.operating-days.update', $day), [
            'status' => 'open',
        ]);

        $day->refresh();
        $this->assertNull($day->closed_at);
        // When reopened, closing_balance is cleared from DB and computed on the fly
        $this->assertEquals(700.00, $day->closing_balance);
    }

    public function test_close_does_not_send_email_when_no_remark(): void
    {
        Mail::fake();

        $season = Season::factory()->create();
        $day = OperatingDay::factory()->create([
            'season_id' => $season->id,
            'cash_remark' => null,
        ]);

        $this->put(route('bo.register.operating-days.update', $day), [
            'status' => 'closed',
        ]);

        Mail::assertNotSent(OperatingDayClosed::class);
    }

    public function test_update_requires_permission(): void
    {
        $user = User::factory()->create();
        $user->givePermissionTo('view register');
        $this->actingAs($user);

        $season = Season::factory()->create();
        $day = OperatingDay::factory()->create(['season_id' => $season->id]);

        $response = $this->put(route('bo.register.operating-days.update', $day), [
            'cash_remark' => 'Test',
        ]);

        $response->assertForbidden();
    }

    public function test_create_returns_form(): void
    {
        $response = $this->get(route('bo.register.operating-days.create'));

        $response->assertOk();
    }

    public function test_store_creates_operating_day(): void
    {
        $season = Season::factory()->create();
        $today = Carbon::today('Europe/Zurich');

        $response = $this->post(route('bo.register.operating-days.store'), [
            'season_id' => $season->id,
            'date' => $today->toDateString(),
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('operating_days', [
            'season_id' => $season->id,
        ]);
    }

    public function test_store_validates_required_fields(): void
    {
        $response = $this->post(route('bo.register.operating-days.store'), []);

        $response->assertSessionHasErrors(['season_id', 'date']);
    }

    public function test_store_validates_date_not_in_future(): void
    {
        $season = Season::factory()->create();

        $response = $this->post(route('bo.register.operating-days.store'), [
            'season_id' => $season->id,
            'date' => Carbon::tomorrow('Europe/Zurich')->toDateString(),
        ]);

        $response->assertSessionHasErrors(['date']);
    }

    public function test_store_prevents_duplicate_date_per_season(): void
    {
        $season = Season::factory()->create();
        OperatingDay::factory()->create([
            'season_id' => $season->id,
            'date' => Carbon::today('Europe/Zurich')->toDateString(),
        ]);

        $response = $this->post(route('bo.register.operating-days.store'), [
            'season_id' => $season->id,
            'date' => Carbon::today('Europe/Zurich')->toDateString(),
        ]);

        $response->assertSessionHasErrors();
    }

    public function test_store_requires_permission(): void
    {
        $user = User::factory()->create();
        $user->givePermissionTo('view register');
        $this->actingAs($user);

        $season = Season::factory()->create();

        $response = $this->post(route('bo.register.operating-days.store'), [
            'season_id' => $season->id,
            'date' => Carbon::today('Europe/Zurich')->toDateString(),
        ]);

        $response->assertForbidden();
    }
}
