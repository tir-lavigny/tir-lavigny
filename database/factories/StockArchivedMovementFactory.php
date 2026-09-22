<?php

namespace Database\Factories;

use App\Models\Product;
use App\Models\Season;
use App\Models\StockArchivedMovement;
use App\Models\StockLocation;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<StockArchivedMovement>
 */
class StockArchivedMovementFactory extends Factory
{
    protected $model = StockArchivedMovement::class;

    public function definition(): array
    {
        return [
            'stock_location_id' => StockLocation::factory(),
            'product_id' => Product::factory(),
            'season_id' => Season::factory(),
            'quantity' => fake()->numberBetween(-50, 50),
            'type' => fake()->randomElement(['initial', 'purchase', 'sale', 'return', 'transfer', 'loss', 'adjustment', 'reversal']),
            'reference_type' => null,
            'reference_id' => null,
            'reason' => fake()->sentence(),
            'original_created_at' => now()->subDays(10),
            'original_updated_at' => now()->subDays(10),
        ];
    }
}
