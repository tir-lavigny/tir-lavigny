<?php

namespace Tests\Unit;

use App\Models\EventChain;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Tests for EventChain::getOccurrenceDates() — the core recurrence engine.
 * Uses an in-memory approach (no HTTP) so it also validates model-level logic.
 */
class EventChainRecurrenceTest extends TestCase
{
    use RefreshDatabase;

    private function makeChain(array $attributes): EventChain
    {
        // Use make() to avoid persisting and triggering the observer
        return EventChain::make(array_merge([
            'title' => 'Test',
            'recurrence_type' => 'weekly',
            'interval' => 1,
            'interval_unit' => null,
            'day_of_week' => null,
            'day_of_month' => null,
            'start_date' => '2099-01-01',
            'end_date' => '2099-01-31',
            'start_time' => '18:00:00',
            'end_time' => '20:00:00',
            'location_id' => null,
            'content_id' => null,
            'custom_body' => null,
        ], $attributes));
    }

    // ─── Daily ───────────────────────────────────────────────────────────────

    public function test_daily_recurrence_generates_correct_count(): void
    {
        $chain = $this->makeChain([
            'recurrence_type' => 'daily',
            'start_date' => '2099-01-01',
            'end_date' => '2099-01-07',
        ]);

        $dates = $chain->getOccurrenceDates();

        $this->assertCount(7, $dates);
    }

    public function test_daily_recurrence_first_and_last_date(): void
    {
        $chain = $this->makeChain([
            'recurrence_type' => 'daily',
            'start_date' => '2099-01-01',
            'end_date' => '2099-01-05',
        ]);

        $dates = $chain->getOccurrenceDates();

        $this->assertEquals('2099-01-01', $dates[0]->format('Y-m-d'));
        $this->assertEquals('2099-01-05', end($dates)->format('Y-m-d'));
    }

    public function test_daily_single_day_range_generates_one_date(): void
    {
        $chain = $this->makeChain([
            'recurrence_type' => 'daily',
            'start_date' => '2099-03-15',
            'end_date' => '2099-03-15',
        ]);

        $this->assertCount(1, $chain->getOccurrenceDates());
    }

    // ─── Weekly ──────────────────────────────────────────────────────────────

    public function test_weekly_recurrence_lands_on_correct_day_of_week(): void
    {
        // 0=Monday in our convention
        $chain = $this->makeChain([
            'recurrence_type' => 'weekly',
            'day_of_week' => 0, // Monday
            'start_date' => '2099-01-05', // Sunday
            'end_date' => '2099-01-31',
        ]);

        $dates = $chain->getOccurrenceDates();

        foreach ($dates as $date) {
            // Carbon: dayOfWeek 1 = Monday
            $this->assertEquals(1, $date->dayOfWeek, 'Expected Monday, got '.$date->format('l'));
        }
    }

    public function test_weekly_recurrence_generates_weekly_interval(): void
    {
        $chain = $this->makeChain([
            'recurrence_type' => 'weekly',
            'day_of_week' => 3, // Thursday (0=Mon)
            'start_date' => '2099-01-06', // Tuesday in 2099
            'end_date' => '2099-01-31',
        ]);

        $dates = $chain->getOccurrenceDates();

        $this->assertGreaterThanOrEqual(2, count($dates));

        // Consecutive occurrences must always be exactly 7 days apart
        for ($i = 1; $i < count($dates); $i++) {
            $diff = $dates[$i - 1]->diffInDays($dates[$i]); // earlier → later = positive
            $this->assertEquals(7, $diff);
        }
    }

    public function test_weekly_recurrence_respects_end_date(): void
    {
        // Use a range that lands on exactly 2 Thursdays.
        // Jan 6, 2099 is a Tuesday; first Thursday = Jan 8.
        // Next: Jan 15, Jan 22. Set end_date to Jan 15 to get exactly 2.
        $chain = $this->makeChain([
            'recurrence_type' => 'weekly',
            'day_of_week' => 3, // Thursday
            'start_date' => '2099-01-06',
            'end_date' => '2099-01-15',
        ]);

        $dates = $chain->getOccurrenceDates();

        $this->assertCount(2, $dates); // Jan 8, 15
    }

    // ─── Biweekly ────────────────────────────────────────────────────────────

    public function test_biweekly_recurrence_generates_14_day_intervals(): void
    {
        $chain = $this->makeChain([
            'recurrence_type' => 'biweekly',
            'day_of_week' => 4, // Friday (0=Mon)
            'start_date' => '2099-01-01',
            'end_date' => '2099-03-01',
        ]);

        $dates = $chain->getOccurrenceDates();

        $this->assertGreaterThanOrEqual(2, count($dates));

        for ($i = 1; $i < count($dates); $i++) {
            // earlier -> later = positive difference
            $this->assertEquals(14, $dates[$i - 1]->diffInDays($dates[$i]));
        }
    }

    // ─── Monthly ─────────────────────────────────────────────────────────────

    public function test_monthly_recurrence_lands_on_correct_day_of_month(): void
    {
        $chain = $this->makeChain([
            'recurrence_type' => 'monthly',
            'day_of_month' => 15,
            'start_date' => '2099-01-01',
            'end_date' => '2099-04-30',
        ]);

        $dates = $chain->getOccurrenceDates();

        $this->assertCount(4, $dates);
        foreach ($dates as $date) {
            $this->assertEquals(15, $date->day);
        }
    }

    public function test_monthly_recurrence_clamps_to_last_day_of_short_month(): void
    {
        $chain = $this->makeChain([
            'recurrence_type' => 'monthly',
            'day_of_month' => 31,
            'start_date' => '2099-01-01',
            'end_date' => '2099-03-31',
        ]);

        $dates = $chain->getOccurrenceDates();

        // Jan 31, Feb 28 (clamped), Mar 31
        $this->assertCount(3, $dates);
        $this->assertEquals('2099-01-31', $dates[0]->format('Y-m-d'));
        $this->assertEquals('2099-02-28', $dates[1]->format('Y-m-d'));
        $this->assertEquals('2099-03-31', $dates[2]->format('Y-m-d'));
    }

    // ─── Custom ───────────────────────────────────────────────────────────────

    public function test_custom_interval_in_days(): void
    {
        $chain = $this->makeChain([
            'recurrence_type' => 'custom',
            'interval' => 3,
            'interval_unit' => 'days',
            'start_date' => '2099-01-01',
            'end_date' => '2099-01-10',
        ]);

        $dates = $chain->getOccurrenceDates();

        $this->assertCount(4, $dates); // Jan 1, 4, 7, 10
        $this->assertEquals('2099-01-01', $dates[0]->format('Y-m-d'));
        $this->assertEquals('2099-01-04', $dates[1]->format('Y-m-d'));
        $this->assertEquals('2099-01-07', $dates[2]->format('Y-m-d'));
        $this->assertEquals('2099-01-10', $dates[3]->format('Y-m-d'));
    }

    public function test_custom_interval_in_weeks(): void
    {
        $chain = $this->makeChain([
            'recurrence_type' => 'custom',
            'interval' => 2,
            'interval_unit' => 'weeks',
            'start_date' => '2099-01-01',
            'end_date' => '2099-01-28',
        ]);

        $dates = $chain->getOccurrenceDates();

        $this->assertCount(2, $dates); // Jan 1, Jan 15
        $this->assertEquals('2099-01-01', $dates[0]->format('Y-m-d'));
        $this->assertEquals('2099-01-15', $dates[1]->format('Y-m-d'));
    }

    // ─── Edge cases ───────────────────────────────────────────────────────────

    public function test_return_empty_array_when_no_occurrence_in_range(): void
    {
        // Jan 6, 2099 = Tuesday; day_of_week=6 = Sunday.
        // First Sunday from Jan 6 is Jan 10. Set end_date to Jan 9 → no Sunday in range.
        $chain = $this->makeChain([
            'recurrence_type' => 'weekly',
            'day_of_week' => 6, // Sunday
            'start_date' => '2099-01-06',
            'end_date' => '2099-01-09', // Saturday — no Sunday in this 4-day span
        ]);

        $this->assertEmpty($chain->getOccurrenceDates());
    }
}
