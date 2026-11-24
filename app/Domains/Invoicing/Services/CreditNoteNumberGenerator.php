<?php

namespace App\Domains\Invoicing\Services;

use App\Domains\Invoicing\Exceptions\InvoiceNumberSequenceExhausted;
use App\Domains\Invoicing\Models\CreditNoteSequence;
use Illuminate\Support\Facades\DB;

class CreditNoteNumberGenerator
{
    public function allocate(int $year): string
    {
        return DB::transaction(function () use ($year): string {
            $sequence = CreditNoteSequence::query()
                ->where('year', $year)
                ->lockForUpdate()
                ->first();

            if ($sequence === null) {
                CreditNoteSequence::query()->insertOrIgnore([
                    'year' => $year,
                    'last_number' => 0,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);

                $sequence = CreditNoteSequence::query()
                    ->where('year', $year)
                    ->lockForUpdate()
                    ->firstOrFail();
            }

            $nextNumber = $sequence->last_number + 1;

            if ($nextNumber > 9999) {
                throw InvoiceNumberSequenceExhausted::forYear($year);
            }

            $sequence->update(['last_number' => $nextNumber]);

            return sprintf('CN-%d-%04d', $year, $nextNumber);
        }, 5);
    }
}
