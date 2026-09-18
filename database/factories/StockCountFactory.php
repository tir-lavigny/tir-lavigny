<?php

namespace Database\Factories;

use App\Enums\StockCountType;
use App\Models\Season;
use App\Models\StockCount;
use App\Models\StockLocation;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<StockCount>
 */
class StockCountFactory extends Factory
{
    protected $model = StockCount::class;

    public function definition(): array
    {
        return [
            'season_id' => Season::factory(),
            'stock_location_id' => StockLocation::factory(),
            'type' => fake()->randomElement(StockCountType::cases()),
            'status' => fake()->randomElement(['pending', 'in_progress', 'completed']),
            'completed_at' => null,
        ];
    }

    public function initial(): static
    {
        return $this->state(fn () => ['type' => StockCountType::Initial]);
    }

    public function adjustment(): static
    {
        return $this->state(fn () => ['type' => StockCountType::Adjustment]);
    }

    public function final(): static
    {
        return $this->state(fn () => ['type' => StockCountType::Final]);
    }

    public function completed(): static
    {
        return $this->state(fn () => [
            'status' => 'completed',
            'completed_at' => fake()->dateTimeThisYear(),
        ]);
    }
}
