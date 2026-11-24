<?php

namespace Database\Factories\Domains\Invoicing\Models;

use App\Domains\Invoicing\Enums\DeliveryStatus;
use App\Domains\Invoicing\Enums\InvoiceDeliveryMethod;
use App\Domains\Invoicing\Models\Invoice;
use App\Domains\Invoicing\Models\InvoiceReminder;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<InvoiceReminder>
 */
class InvoiceReminderFactory extends Factory
{
    protected $model = InvoiceReminder::class;

    public function definition(): array
    {
        return [
            'invoice_id' => Invoice::factory(),
            'channel' => InvoiceDeliveryMethod::Postal,
            'recipient' => fake()->address(),
            'outstanding_amount_cents' => fake()->numberBetween(100, 30000),
            'status' => DeliveryStatus::Pending,
            'processed_at' => null,
            'processed_by' => null,
        ];
    }
}
