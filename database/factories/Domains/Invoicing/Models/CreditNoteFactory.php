<?php

namespace Database\Factories\Domains\Invoicing\Models;

use App\Domains\Invoicing\Enums\CreditNoteStatus;
use App\Domains\Invoicing\Enums\Currency;
use App\Domains\Invoicing\Models\BillingCustomer;
use App\Domains\Invoicing\Models\CreditNote;
use App\Domains\Invoicing\Models\Invoice;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CreditNote>
 */
class CreditNoteFactory extends Factory
{
    protected $model = CreditNote::class;

    public function definition(): array
    {
        $billingCustomer = BillingCustomer::factory()->create();
        $invoice = Invoice::factory()->create([
            'billing_customer_id' => $billingCustomer->id,
            'status' => 'issued',
        ]);

        return [
            'invoice_id' => $invoice->id,
            'billing_customer_id' => $billingCustomer->id,
            'credit_note_number' => null,
            'status' => CreditNoteStatus::Draft,
            'reason' => fake()->sentence(),
            'issue_date' => null,
            'currency' => Currency::CHF,
            'subtotal_cents' => 10000,
            'total_cents' => 10000,
            'recipient_name' => $billingCustomer->displayName(),
            'recipient_address' => $billingCustomer->address,
            'recipient_postal_code' => $billingCustomer->postal_code,
            'recipient_city' => $billingCustomer->city,
            'recipient_country' => $billingCustomer->country,
            'recipient_email' => $billingCustomer->email,
            'creditor_name' => null,
            'creditor_address' => null,
            'creditor_postal_code' => null,
            'creditor_city' => null,
            'creditor_country' => null,
            'creditor_iban' => null,
            'created_by' => User::factory(),
            'issued_at' => null,
        ];
    }
}
