<?php

namespace Database\Factories;

use App\Models\OperatingDay;
use App\Models\Season;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<OperatingDay>
 */
class OperatingDayFactory extends Factory
{
    public function definition(): array
    {
        $date = fake()->dateTimeBetween('-1 year', 'now');

        return [
            'date' => $date,
            'started_at' => $date,
            'closed_at' => null,
            'season_id' => Season::factory(),
            'cash_remark' => null,
            'closing_balance' => null,
            'total_cash' => 0,
            'total_twint' => 0,
        ];
    }

    public function closed(): static
    {
        return $this->state(fn (array $attributes) => [
            'closed_at' => (clone $attributes['started_at'])->modify('+8 hours'),
            'closing_balance' => fake()->randomFloat(2, 50, 500),
        ]);
    }
}
