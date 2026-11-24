<?php

namespace Database\Factories\Domains\Invoicing\Models;

use App\Domains\Invoicing\Models\Invoice;
use App\Domains\Invoicing\Models\InvoiceActivityLog;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<InvoiceActivityLog>
 */
class InvoiceActivityLogFactory extends Factory
{
    protected $model = InvoiceActivityLog::class;

    public function definition(): array
    {
        return [
            'invoice_id' => Invoice::factory(),
            'user_id' => User::factory(),
            'action' => 'created',
            'description' => null,
            'metadata' => null,
        ];
    }
}
