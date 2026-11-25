<?php

namespace Database\Factories\Domains\Invoicing\Models;

use App\Domains\Invoicing\Models\MembershipFeeType;
use App\MemberStatus;
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
            'member_status' => MemberStatus::ACTIVE,
            'description' => null,
            'amount_cents' => fake()->numberBetween(1000, 30000),
        ];
    }

    public function passive(): static
    {
        return $this->state(fn () => [
            'name' => 'Cotisation membre passif',
            'member_status' => MemberStatus::PASSIVE,
        ]);
    }
}
