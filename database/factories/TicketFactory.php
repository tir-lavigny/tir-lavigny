<?php

namespace Database\Factories;

use App\Models\OperatingDay;
use App\Models\Ticket;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Ticket>
 */
class TicketFactory extends Factory
{
    public function definition(): array
    {
        return [
            'total' => fake()->randomFloat(2, 1, 100),
            'payment_method' => fake()->randomElement(['cash', 'twint']),
            'operating_day_id' => OperatingDay::factory(),
        ];
    }

    public function cash(): static
    {
        return $this->state(fn () => ['payment_method' => 'cash']);
    }

    public function twint(): static
    {
        return $this->state(fn () => ['payment_method' => 'twint']);
    }
}
