<?php

namespace Database\Factories;

use App\Models\CashMovement;
use App\Models\Season;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CashMovement>
 */
class CashMovementFactory extends Factory
{
    protected $model = CashMovement::class;

    public function definition(): array
    {
        return [
            'season_id' => Season::factory(),
            'amount' => fake()->randomFloat(2, 10, 500),
            'occurred_at' => now()->toDateString(),
            'remark' => null,
        ];
    }

    public function withdrawal(): static
    {
        return $this->state(fn () => ['amount' => -1 * fake()->randomFloat(2, 10, 500)]);
    }

    public function injection(): static
    {
        return $this->state(fn () => ['amount' => fake()->randomFloat(2, 10, 500)]);
    }
}
