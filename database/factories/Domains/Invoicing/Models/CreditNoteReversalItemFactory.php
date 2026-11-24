<?php

namespace Database\Factories\Domains\Invoicing\Models;

use App\Domains\Invoicing\Models\CreditNoteReversal;
use App\Domains\Invoicing\Models\CreditNoteReversalItem;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CreditNoteReversalItem>
 */
class CreditNoteReversalItemFactory extends Factory
{
    protected $model = CreditNoteReversalItem::class;

    public function definition(): array
    {
        return [
            'credit_note_reversal_id' => CreditNoteReversal::factory(),
            'credit_note_item_id' => null,
            'description' => fake()->sentence(3),
            'amount_cents' => fake()->numberBetween(100, 10000),
            'position' => fake()->numberBetween(1, 10),
        ];
    }
}
