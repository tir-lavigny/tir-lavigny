<?php

namespace Database\Factories\Domains\Invoicing\Models;

use App\Domains\Invoicing\Models\Invoice;
use App\Domains\Invoicing\Models\InvoiceDocument;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<InvoiceDocument>
 */
class InvoiceDocumentFactory extends Factory
{
    protected $model = InvoiceDocument::class;

    public function definition(): array
    {
        return [
            'invoice_id' => Invoice::factory(),
            'path' => 'invoices/'.now()->format('Y').'/'.fake()->uuid().'.pdf',
            'sha256' => hash('sha256', fake()->uuid()),
            'generated_at' => now(),
        ];
    }
}
