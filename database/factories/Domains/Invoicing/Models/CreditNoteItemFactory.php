<?php

namespace Database\Factories\Domains\Invoicing\Models;

use App\Domains\Invoicing\Models\CreditNote;
use App\Domains\Invoicing\Models\CreditNoteItem;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CreditNoteItem>
 */
class CreditNoteItemFactory extends Factory
{
    protected $model = CreditNoteItem::class;

    public function definition(): array
    {
        $unitPrice = fake()->numberBetween(100, 20000);

        return [
            'credit_note_id' => CreditNote::factory(),
            'invoice_item_id' => null,
            'description' => fake()->sentence(3),
            'quantity' => '1.000',
            'unit_price_cents' => $unitPrice,
            'total_cents' => $unitPrice,
            'position' => fake()->numberBetween(1, 10),
            'metadata' => null,
        ];
    }
}
