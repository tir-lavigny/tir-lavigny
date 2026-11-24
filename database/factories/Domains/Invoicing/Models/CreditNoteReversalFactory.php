<?php

namespace Database\Factories\Domains\Invoicing\Models;

use App\Domains\Invoicing\Enums\CreditNoteReversalStatus;
use App\Domains\Invoicing\Enums\Currency;
use App\Domains\Invoicing\Models\CreditNote;
use App\Domains\Invoicing\Models\CreditNoteReversal;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CreditNoteReversal>
 */
class CreditNoteReversalFactory extends Factory
{
    protected $model = CreditNoteReversal::class;

    public function definition(): array
    {
        $creditNote = CreditNote::factory()->create();

        return [
            'credit_note_id' => $creditNote->id,
            'credit_note_reversal_number' => null,
            'status' => CreditNoteReversalStatus::Draft,
            'reason' => fake()->sentence(),
            'issue_date' => null,
            'currency' => Currency::CHF,
            'total_cents' => 0,
            'recipient_name' => $creditNote->recipient_name,
            'recipient_address' => $creditNote->recipient_address,
            'recipient_postal_code' => $creditNote->recipient_postal_code,
            'recipient_city' => $creditNote->recipient_city,
            'recipient_country' => $creditNote->recipient_country,
            'recipient_email' => $creditNote->recipient_email,
            'creditor_name' => $creditNote->creditor_name,
            'creditor_address' => $creditNote->creditor_address,
            'creditor_postal_code' => $creditNote->creditor_postal_code,
            'creditor_city' => $creditNote->creditor_city,
            'creditor_country' => $creditNote->creditor_country,
            'creditor_iban' => $creditNote->creditor_iban,
            'created_by' => User::factory(),
            'issued_at' => null,
        ];
    }
}
