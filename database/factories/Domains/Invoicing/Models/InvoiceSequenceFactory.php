<?php

namespace Database\Factories\Domains\Invoicing\Models;

use App\Domains\Invoicing\Models\InvoiceSequence;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<InvoiceSequence>
 */
class InvoiceSequenceFactory extends Factory
{
    protected $model = InvoiceSequence::class;

    public function definition(): array
    {
        return [
            'year' => (int) now()->format('Y'),
            'last_number' => 0,
        ];
    }
}
