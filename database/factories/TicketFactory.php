<?php

namespace Database\Factories;

use App\Models\OperatingDay;
use App\Models\StockLocation;
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
            'total_cents' => fake()->numberBetween(100, 10000),
            'payment_method' => fake()->randomElement(['cash', 'twint']),
            'operating_day_id' => OperatingDay::factory(),
            'member_id' => null,
            'sold_at' => fake()->dateTimeThisYear(),
            'stock_location_id' => StockLocation::factory(),
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
