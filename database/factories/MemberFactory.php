<?php

namespace Database\Factories;

use App\MemberStatus;
use App\Models\Member;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Member>
 */
class MemberFactory extends Factory
{
    public function definition(): array
    {
        return [
            'first_name' => fake()->firstName(),
            'last_name' => fake()->lastName(),
            'email' => fake()->safeEmail(),
            'tel' => fake()->phoneNumber(),
            'avs' => null,
            'is_swiss_citizen' => true,
            'street' => fake()->streetAddress(),
            'npa' => fake()->numerify('####'),
            'city' => fake()->city(),
            'canton' => 'VD',
            'country' => 'Suisse',
            'status' => MemberStatus::ACTIVE,
            'dav_uid' => null,
        ];
    }

    public function passive(): static
    {
        return $this->state(fn () => ['status' => MemberStatus::PASSIVE]);
    }

    public function archived(): static
    {
        return $this->state(fn () => ['deleted_at' => now()]);
    }
}
