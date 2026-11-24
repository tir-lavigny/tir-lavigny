<?php

namespace Database\Factories\Domains\Invoicing\Models;

use App\Domains\Invoicing\Models\CreditNoteApplication;
use App\Domains\Invoicing\Models\CreditNoteApplicationReversal;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CreditNoteApplicationReversal>
 */
class CreditNoteApplicationReversalFactory extends Factory
{
    protected $model = CreditNoteApplicationReversal::class;

    public function definition(): array
    {
        return [
            'credit_note_application_id' => CreditNoteApplication::factory(),
            'amount_cents' => fake()->numberBetween(100, 10000),
            'reason' => fake()->sentence(),
            'reversed_at' => now(),
            'created_by' => User::factory(),
        ];
    }
}
