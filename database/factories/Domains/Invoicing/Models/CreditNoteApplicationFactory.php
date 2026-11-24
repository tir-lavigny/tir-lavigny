<?php

namespace Database\Factories\Domains\Invoicing\Models;

use App\Domains\Invoicing\Models\CreditNote;
use App\Domains\Invoicing\Models\CreditNoteApplication;
use App\Domains\Invoicing\Models\Invoice;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CreditNoteApplication>
 */
class CreditNoteApplicationFactory extends Factory
{
    protected $model = CreditNoteApplication::class;

    public function definition(): array
    {
        return [
            'credit_note_id' => CreditNote::factory(),
            'invoice_id' => Invoice::factory(),
            'amount_cents' => fake()->numberBetween(100, 10000),
            'applied_at' => now(),
            'created_by' => User::factory(),
        ];
    }
}
