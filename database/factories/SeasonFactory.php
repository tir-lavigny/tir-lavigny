<?php

namespace Database\Factories;

use App\Models\Season;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Season>
 */
class SeasonFactory extends Factory
{
    public function definition(): array
    {
        $start = fake()->dateTimeBetween('-1 year', 'now');

        return [
            'name' => fake()->year().' Season',
            'start_date' => $start,
            'end_date' => (clone $start)->modify('+3 months'),
            'initial_balance' => fake()->randomFloat(2, 0, 500),
            'end_balance' => null,
        ];
    }
}
