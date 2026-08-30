<?php

namespace Database\Factories;

use App\Models\Member;
use App\Models\Product;
use App\Models\Season;
use App\Models\Voucher;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Voucher>
 */
class VoucherFactory extends Factory
{
    public function definition(): array
    {
        return [
            'discount_percent' => fake()->randomElement([5, 10, 15, 20, null]),
            'discount_fixed' => fn (array $attributes) => $attributes['discount_percent'] === null
                ? fake()->randomFloat(2, 1, 10)
                : null,
            'product_id' => Product::factory(),
            'season_id' => Season::factory(),
            'member_id' => Member::factory(),
        ];
    }

    public function percent(): static
    {
        return $this->state(fn () => [
            'discount_percent' => fake()->randomElement([5, 10, 15, 20]),
            'discount_fixed' => null,
        ]);
    }

    public function fixed(): static
    {
        return $this->state(fn () => [
            'discount_percent' => null,
            'discount_fixed' => fake()->randomFloat(2, 1, 10),
        ]);
    }
}
