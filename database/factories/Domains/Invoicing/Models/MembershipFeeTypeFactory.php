<?php

namespace Database\Factories\Domains\Invoicing\Models;

use App\Domains\Invoicing\Models\MembershipFeeType;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<MembershipFeeType>
 */
class MembershipFeeTypeFactory extends Factory
{
    protected $model = MembershipFeeType::class;

    public function definition(): array
    {
        return [
            'name' => fake()->unique()->words(2, true),
            'description' => null,
            'amount_cents' => fake()->numberBetween(1000, 30000),
            'is_active' => true,
        ];
    }
}
