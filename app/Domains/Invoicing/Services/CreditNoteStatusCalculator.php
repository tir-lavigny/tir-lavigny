<?php

namespace App\Domains\Invoicing\Services;

use App\Domains\Invoicing\Enums\CreditNoteStatus;
use App\Domains\Invoicing\Models\CreditNote;

class CreditNoteStatusCalculator
{
    public function __construct(
        private readonly CreditNoteBalanceCalculator $balances,
    ) {}

    public function calculate(CreditNote $creditNote): CreditNoteStatus
    {
        if ($creditNote->isDraft()) {
            return CreditNoteStatus::Draft;
        }

        $reversed = $this->balances->reversedCents($creditNote);

        if ($reversed > 0 && $reversed >= $creditNote->total_cents) {
            return CreditNoteStatus::Reversed;
        }

        if ($reversed > 0) {
            return CreditNoteStatus::PartiallyReversed;
        }

        if ($this->balances->availableCents($creditNote) <= 0) {
            return CreditNoteStatus::FullyUsed;
        }

        if ($this->balances->appliedCents($creditNote) > 0 || $this->balances->refundedCents($creditNote) > 0) {
            return CreditNoteStatus::PartiallyUsed;
        }

        return CreditNoteStatus::Issued;
    }

    public function refresh(CreditNote $creditNote): CreditNote
    {
        $status = $this->calculate($creditNote);

        if ($creditNote->status !== $status) {
            $creditNote->update(['status' => $status]);
        }

        return $creditNote->refresh();
    }
}
