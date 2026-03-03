<?php

namespace Database\Factories;

use App\Models\EventChain;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<EventChain>
 */
class EventChainFactory extends Factory
{
    public function definition(): array
    {
        $startDate = $this->faker->dateTimeBetween('now', '+1 month');
        $endDate = (clone $startDate)->modify('+3 months');

        return [
            'title' => $this->faker->sentence(4),
            'recurrence_type' => 'weekly',
            'interval' => 1,
            'interval_unit' => null,
            'day_of_week' => $this->faker->numberBetween(0, 6), // 0=Mon … 6=Sun
            'day_of_month' => null,
            'start_date' => $startDate->format('Y-m-d'),
            'end_date' => $endDate->format('Y-m-d'),
            'start_time' => '18:00:00',
            'end_time' => '20:00:00',
            'location_id' => null,
            'content_id' => null,
            'custom_body' => null,
        ];
    }

    /**
     * Chain that recurs daily.
     */
    public function daily(): static
    {
        return $this->state([
            'recurrence_type' => 'daily',
            'day_of_week' => null,
        ]);
    }

    /**
     * Chain that recurs weekly on a given day (0=Mon … 6=Sun).
     */
    public function weekly(int $dayOfWeek = 3): static
    {
        return $this->state([
            'recurrence_type' => 'weekly',
            'day_of_week' => $dayOfWeek,
        ]);
    }

    /**
     * Chain that recurs monthly.
     */
    public function monthly(int $dayOfMonth = 1): static
    {
        return $this->state([
            'recurrence_type' => 'monthly',
            'day_of_week' => null,
            'day_of_month' => $dayOfMonth,
        ]);
    }

    /**
     * Chain that uses a custom interval (e.g. every 2 weeks).
     */
    public function custom(int $interval, string $unit = 'weeks'): static
    {
        return $this->state([
            'recurrence_type' => 'custom',
            'interval' => $interval,
            'interval_unit' => $unit,
            'day_of_week' => null,
        ]);
    }

    /**
     * Disable event generation by making the range zero-length.
     * Useful when you want a chain record without triggering the observer.
     */
    public function withoutEvents(): static
    {
        return $this->state(function (array $attributes) {
            return [
                'start_date' => '2099-01-01',
                'end_date' => '2099-01-01',
                'day_of_week' => 0, // Monday
            ];
        });
    }
}
