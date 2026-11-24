<?php

namespace Database\Factories\Domains\Invoicing\Models;

use App\Domains\Invoicing\Enums\DeliveryStatus;
use App\Domains\Invoicing\Enums\InvoiceDeliveryMethod;
use App\Domains\Invoicing\Models\Invoice;
use App\Domains\Invoicing\Models\InvoiceDelivery;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<InvoiceDelivery>
 */
class InvoiceDeliveryFactory extends Factory
{
    protected $model = InvoiceDelivery::class;

    public function definition(): array
    {
        return [
            'invoice_id' => Invoice::factory(),
            'channel' => InvoiceDeliveryMethod::Email,
            'recipient' => fake()->safeEmail(),
            'status' => DeliveryStatus::Pending,
            'processed_at' => null,
            'processed_by' => null,
            'notes' => null,
        ];
    }
}
