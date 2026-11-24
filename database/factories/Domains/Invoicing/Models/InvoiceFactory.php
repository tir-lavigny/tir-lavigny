<?php

namespace Database\Factories\Domains\Invoicing\Models;

use App\Domains\Invoicing\Enums\Currency;
use App\Domains\Invoicing\Enums\InvoiceDeliveryMethod;
use App\Domains\Invoicing\Enums\InvoiceStatus;
use App\Domains\Invoicing\Enums\InvoiceType;
use App\Domains\Invoicing\Models\BillingCustomer;
use App\Domains\Invoicing\Models\Invoice;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Invoice>
 */
class InvoiceFactory extends Factory
{
    protected $model = Invoice::class;

    public function definition(): array
    {
        return [
            'billing_customer_id' => BillingCustomer::factory(),
            'membership_fee_campaign_id' => null,
            'type' => InvoiceType::OneOff,
            'status' => InvoiceStatus::Draft,
            'invoice_number' => null,
            'recipient_name' => fake()->name(),
            'recipient_address' => fake()->streetAddress(),
            'recipient_postal_code' => fake()->numerify('####'),
            'recipient_city' => fake()->city(),
            'recipient_country' => 'CH',
            'recipient_email' => fake()->safeEmail(),
            'delivery_method' => InvoiceDeliveryMethod::Email,
            'issue_date' => null,
            'due_date' => null,
            'currency' => Currency::CHF,
            'subtotal_cents' => 0,
            'total_cents' => 0,
            'issued_at' => null,
            'sent_at' => null,
            'cancelled_at' => null,
            'cancelled_by' => null,
            'cancellation_reason' => null,
            'payment_reference' => null,
            'creditor_name' => null,
            'creditor_address' => null,
            'creditor_postal_code' => null,
            'creditor_city' => null,
            'creditor_country' => null,
            'creditor_iban' => null,
            'created_by' => User::factory(),
        ];
    }
}
