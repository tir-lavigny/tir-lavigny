<?php

namespace Database\Factories\Domains\Invoicing\Models;

use App\Domains\Invoicing\Enums\InvoiceItemType;
use App\Domains\Invoicing\Models\Invoice;
use App\Domains\Invoicing\Models\InvoiceItem;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<InvoiceItem>
 */
class InvoiceItemFactory extends Factory
{
    protected $model = InvoiceItem::class;

    public function definition(): array
    {
        $unitPrice = fake()->numberBetween(100, 20000);
        $quantity = fake()->numberBetween(1, 5);

        return [
            'invoice_id' => Invoice::factory(),
            'product_id' => null,
            'type' => InvoiceItemType::Fee,
            'description' => fake()->sentence(3),
            'quantity' => $quantity,
            'unit_price_cents' => $unitPrice,
            'total_cents' => $unitPrice * $quantity,
            'position' => fake()->numberBetween(1, 10),
            'metadata' => null,
        ];
    }
}
