<?php

namespace Database\Factories;

use App\Models\Category;
use App\Models\Product;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Product>
 */
class ProductFactory extends Factory
{
    public function definition(): array
    {
        return [
            'name' => fake()->words(2, true),
            'picture' => null,
            'unit_price' => fake()->randomFloat(2, 1, 50),
            'allows_custom_price' => false,
            'category_id' => Category::factory(),
            'is_stockable' => true,
        ];
    }

    public function allowsCustomPrice(): static
    {
        return $this->state(fn () => ['allows_custom_price' => true]);
    }
}
