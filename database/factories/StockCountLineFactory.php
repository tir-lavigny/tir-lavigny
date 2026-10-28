<?php

namespace Database\Factories;

use App\Models\Product;
use App\Models\StockCount;
use App\Models\StockCountLine;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<StockCountLine>
 */
class StockCountLineFactory extends Factory
{
    protected $model = StockCountLine::class;

    public function definition(): array
    {
        $expected = fake()->numberBetween(0, 100);

        return [
            'stock_count_id' => StockCount::factory(),
            'product_id' => Product::factory(),
            'expected_quantity' => $expected,
            'counted_quantity' => $expected + fake()->numberBetween(-5, 5),
        ];
    }

    public function matched(): static
    {
        return $this->state(fn () => [
            'counted_quantity' => fn (array $attrs) => $attrs['expected_quantity'],
        ]);
    }

    public function withDifference(): static
    {
        return $this->state(fn () => [
            'counted_quantity' => fn (array $attrs) => $attrs['expected_quantity']
                + fake()->numberBetween(1, 10) * (fake()->boolean() ? 1 : -1),
        ]);
    }
}
