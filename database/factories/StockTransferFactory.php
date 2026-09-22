<?php

namespace Database\Factories;

use App\Models\Season;
use App\Models\StockLocation;
use App\Models\StockTransfer;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<StockTransfer>
 */
class StockTransferFactory extends Factory
{
    protected $model = StockTransfer::class;

    public function definition(): array
    {
        return [
            'from_location_id' => StockLocation::factory(),
            'to_location_id' => StockLocation::factory(),
            'season_id' => Season::factory(),
            'reason' => fake()->sentence(),
        ];
    }

    public function withoutSeason(): static
    {
        return $this->state(fn () => ['season_id' => null]);
    }
}
