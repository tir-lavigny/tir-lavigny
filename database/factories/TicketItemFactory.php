<?php

namespace Database\Factories;

use App\Models\Product;
use App\Models\Ticket;
use App\Models\TicketItem;
use App\Models\Voucher;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<TicketItem>
 */
class TicketItemFactory extends Factory
{
    public function definition(): array
    {
        $unitPriceCents = fake()->numberBetween(100, 5000);
        $quantity = fake()->numberBetween(1, 5);

        return [
            'product_id' => Product::factory(),
            'ticket_id' => Ticket::factory(),
            'voucher_id' => null,
            'quantity' => $quantity,
            'unit_price_cents' => $unitPriceCents,
            'subtotal_cents' => $unitPriceCents * $quantity,
        ];
    }

    public function withVoucher(): static
    {
        return $this->state(fn () => [
            'voucher_id' => Voucher::factory(),
        ]);
    }
}
