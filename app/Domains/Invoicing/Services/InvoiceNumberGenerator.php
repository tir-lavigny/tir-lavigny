<?php

namespace App\Domains\Invoicing\Services;

use App\Domains\Invoicing\Exceptions\InvoiceNumberSequenceExhausted;
use App\Domains\Invoicing\Models\InvoiceSequence;
use Illuminate\Support\Facades\DB;

class InvoiceNumberGenerator
{
    public function allocate(int $year): string
    {
        return DB::transaction(function () use ($year): string {
            $sequence = InvoiceSequence::query()
                ->where('year', $year)
                ->lockForUpdate()
                ->first();

            if ($sequence === null) {
                InvoiceSequence::query()->insertOrIgnore([
                    'year' => $year,
                    'last_number' => 0,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);

                $sequence = InvoiceSequence::query()
                    ->where('year', $year)
                    ->lockForUpdate()
                    ->firstOrFail();
            }

            $nextNumber = $sequence->last_number + 1;

            if ($nextNumber > 9999) {
                throw InvoiceNumberSequenceExhausted::forYear($year);
            }

            $sequence->update(['last_number' => $nextNumber]);

            return sprintf('%d-%04d', $year, $nextNumber);
        });
    }
}
