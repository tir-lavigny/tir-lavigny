<?php

namespace Database\Factories\Domains\Invoicing\Models;

use App\Domains\Invoicing\Models\CreditNoteSequence;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CreditNoteSequence>
 */
class CreditNoteSequenceFactory extends Factory
{
    protected $model = CreditNoteSequence::class;

    public function definition(): array
    {
        return [
            'year' => (int) now()->format('Y'),
            'last_number' => 0,
        ];
    }
}
