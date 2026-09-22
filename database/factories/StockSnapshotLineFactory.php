<?php

namespace Database\Factories;

use App\Models\Product;
use App\Models\StockSnapshot;
use App\Models\StockSnapshotLine;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<StockSnapshotLine>
 */
class StockSnapshotLineFactory extends Factory
{
    protected $model = StockSnapshotLine::class;

    public function definition(): array
    {
        return [
            'stock_snapshot_id' => StockSnapshot::factory(),
            'product_id' => Product::factory(),
            'quantity' => fake()->numberBetween(-50, 100),
        ];
    }
}
