<?php

namespace App\Domains\Invoicing\Services;

use App\Domains\Invoicing\Models\CreditNote;
use App\Domains\Invoicing\Models\CreditNoteApplication;

class CreditNoteBalanceCalculator
{
    public function appliedCents(CreditNote $creditNote): int
    {
        return (int) CreditNoteApplication::query()
            ->where('credit_note_id', $creditNote->id)
            ->sum('amount_cents');
    }

    public function refundedCents(CreditNote $creditNote): int
    {
        return 0;
    }

    public function reversedCents(CreditNote $creditNote): int
    {
        return 0;
    }

    public function availableCents(CreditNote $creditNote): int
    {
        return max(
            0,
            $creditNote->total_cents
                - $this->appliedCents($creditNote)
                - $this->refundedCents($creditNote)
                - $this->reversedCents($creditNote),
        );
    }
}
