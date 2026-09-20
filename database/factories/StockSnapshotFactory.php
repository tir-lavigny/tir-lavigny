<?php

namespace Database\Factories;

use App\Models\StockLocation;
use App\Models\StockSnapshot;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<StockSnapshot>
 */
class StockSnapshotFactory extends Factory
{
    protected $model = StockSnapshot::class;

    public function definition(): array
    {
        return [
            'stock_location_id' => StockLocation::factory(),
            'snapshot_at' => now(),
        ];
    }
}
