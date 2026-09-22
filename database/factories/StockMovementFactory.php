<?php

namespace Database\Factories;

use App\Enums\StockMovementType;
use App\Models\Product;
use App\Models\Season;
use App\Models\StockLocation;
use App\Models\StockMovement;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<StockMovement>
 */
class StockMovementFactory extends Factory
{
    protected $model = StockMovement::class;

    public function definition(): array
    {
        return [
            'stock_location_id' => StockLocation::factory(),
            'product_id' => Product::factory(),
            'season_id' => Season::factory(),
            'quantity' => fake()->numberBetween(-50, 50),
            'type' => fake()->randomElement(StockMovementType::cases()),
            'reference_type' => null,
            'reference_id' => null,
            'reason' => fake()->sentence(),
        ];
    }

    public function initial(): static
    {
        return $this->state(fn () => ['type' => StockMovementType::Initial]);
    }

    public function purchase(): static
    {
        return $this->state(fn () => ['type' => StockMovementType::Purchase, 'quantity' => fake()->numberBetween(1, 100)]);
    }

    public function sale(): static
    {
        return $this->state(fn () => ['type' => StockMovementType::Sale, 'quantity' => fake()->numberBetween(-100, -1)]);
    }

    public function loss(): static
    {
        return $this->state(fn () => ['type' => StockMovementType::Loss, 'quantity' => fake()->numberBetween(-50, -1)]);
    }

    public function adjustment(): static
    {
        return $this->state(fn () => ['type' => StockMovementType::Adjustment]);
    }

    public function transfer(): static
    {
        return $this->state(fn () => ['type' => StockMovementType::Transfer]);
    }

    public function withoutSeason(): static
    {
        return $this->state(fn () => ['season_id' => null]);
    }
}
