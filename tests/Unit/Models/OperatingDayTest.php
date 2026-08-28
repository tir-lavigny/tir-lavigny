<?php

namespace Tests\Unit\Models;

use App\Models\OperatingDay;
use App\Models\Ticket;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OperatingDayTest extends TestCase
{
    use RefreshDatabase;

    public function test_is_open_returns_true_when_closed_at_is_null(): void
    {
        $day = OperatingDay::factory()->create(['closed_at' => null]);

        $this->assertTrue($day->isOpen());
    }

    public function test_is_open_returns_false_when_closed_at_is_set(): void
    {
        $day = OperatingDay::factory()->closed()->create();

        $this->assertFalse($day->isOpen());
    }

    public function test_recalculate_totals_sums_cash_tickets(): void
    {
        $day = OperatingDay::factory()->create([
            'total_cash' => 0,
            'total_twint' => 0,
        ]);

        Ticket::factory()->cash()->create([
            'operating_day_id' => $day->id,
            'total' => 25.50,
        ]);
        Ticket::factory()->cash()->create([
            'operating_day_id' => $day->id,
            'total' => 10.00,
        ]);

        $day->recalculateTotals();

        $this->assertEquals('35.50', $day->fresh()->total_cash);
    }

    public function test_recalculate_totals_sums_twint_tickets(): void
    {
        $day = OperatingDay::factory()->create();

        Ticket::factory()->twint()->create([
            'operating_day_id' => $day->id,
            'total' => 50.00,
        ]);
        Ticket::factory()->twint()->create([
            'operating_day_id' => $day->id,
            'total' => 30.00,
        ]);

        $day->recalculateTotals();

        $this->assertEquals('80.00', $day->fresh()->total_twint);
    }

    public function test_recalculate_totals_handles_both_payment_methods(): void
    {
        $day = OperatingDay::factory()->create();

        Ticket::factory()->cash()->create([
            'operating_day_id' => $day->id,
            'total' => 20.00,
        ]);
        Ticket::factory()->twint()->create([
            'operating_day_id' => $day->id,
            'total' => 45.00,
        ]);

        $day->recalculateTotals();

        $day->refresh();
        $this->assertEquals('20.00', $day->total_cash);
        $this->assertEquals('45.00', $day->total_twint);
    }

    public function test_recalculate_totals_excludes_refunded_tickets(): void
    {
        $day = OperatingDay::factory()->create();

        Ticket::factory()->cash()->create([
            'operating_day_id' => $day->id,
            'total' => 100.00,
        ]);
        $refunded = Ticket::factory()->cash()->create([
            'operating_day_id' => $day->id,
            'total' => 50.00,
        ]);
        $refunded->delete();

        $day->recalculateTotals();

        $this->assertEquals('100.00', $day->fresh()->total_cash);
    }

    public function test_recalculate_totals_returns_zero_when_no_tickets(): void
    {
        $day = OperatingDay::factory()->create();

        $day->recalculateTotals();

        $day->refresh();
        $this->assertEquals('0.00', $day->total_cash);
        $this->assertEquals('0.00', $day->total_twint);
    }
}
