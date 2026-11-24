<?php

namespace App\Domains\Invoicing\Services;

use App\Domains\Invoicing\Enums\CreditNoteReversalStatus;
use App\Domains\Invoicing\Models\CreditNote;
use App\Domains\Invoicing\Models\CreditNoteApplication;
use App\Domains\Invoicing\Models\CreditNoteApplicationReversal;
use App\Domains\Invoicing\Models\CreditNoteRefund;
use App\Domains\Invoicing\Models\CreditNoteReversal;

class CreditNoteBalanceCalculator
{
    public function appliedCents(CreditNote $creditNote): int
    {
        $applied = (int) CreditNoteApplication::query()
            ->where('credit_note_id', $creditNote->id)
            ->sum('amount_cents');

        $reversed = (int) CreditNoteApplicationReversal::query()
            ->whereHas('application', fn ($query) => $query->where('credit_note_id', $creditNote->id))
            ->sum('amount_cents');

        return max(0, $applied - $reversed);
    }

    public function refundedCents(CreditNote $creditNote): int
    {
        return (int) CreditNoteRefund::query()
            ->where('credit_note_id', $creditNote->id)
            ->sum('amount_cents');
    }

    public function reversedCents(CreditNote $creditNote): int
    {
        return (int) CreditNoteReversal::query()
            ->where('credit_note_id', $creditNote->id)
            ->where('status', CreditNoteReversalStatus::Issued->value)
            ->sum('total_cents');
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
