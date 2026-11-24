<?php

namespace Database\Factories\Domains\Invoicing\Models;

use App\Domains\Invoicing\Models\CreditNoteReversalSequence;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CreditNoteReversalSequence>
 */
class CreditNoteReversalSequenceFactory extends Factory
{
    protected $model = CreditNoteReversalSequence::class;

    public function definition(): array
    {
        return [
            'year' => (int) now()->format('Y'),
            'last_number' => 0,
        ];
    }
}
