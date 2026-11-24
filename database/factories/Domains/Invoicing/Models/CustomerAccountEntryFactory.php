<?php

namespace Database\Factories\Domains\Invoicing\Models;

use App\Domains\Invoicing\Enums\CustomerAccountEntryType;
use App\Domains\Invoicing\Models\BillingCustomer;
use App\Domains\Invoicing\Models\CustomerAccountEntry;
use App\Domains\Invoicing\Models\Invoice;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CustomerAccountEntry>
 */
class CustomerAccountEntryFactory extends Factory
{
    protected $model = CustomerAccountEntry::class;

    public function definition(): array
    {
        return [
            'billing_customer_id' => BillingCustomer::factory(),
            'invoice_id' => Invoice::factory(),
            'type' => CustomerAccountEntryType::InvoiceIssued,
            'debit_cents' => 1000,
            'credit_cents' => 0,
            'occurred_at' => now(),
            'created_by' => User::factory(),
            'metadata' => null,
        ];
    }
}
