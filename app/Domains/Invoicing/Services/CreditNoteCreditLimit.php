<?php

namespace App\Domains\Invoicing\Services;

use App\Domains\Invoicing\Enums\CreditNoteReversalStatus;
use App\Domains\Invoicing\Models\CreditNote;
use App\Domains\Invoicing\Models\CreditNoteReversal;
use App\Domains\Invoicing\Models\Invoice;

class CreditNoteCreditLimit
{
    public function remainingFor(Invoice $invoice): int
    {
        $credited = (int) CreditNote::query()
            ->where('invoice_id', $invoice->id)
            ->where('status', '!=', 'draft')
            ->sum('total_cents');

        $reversed = (int) CreditNoteReversal::query()
            ->whereHas('creditNote', fn ($query) => $query->where('invoice_id', $invoice->id))
            ->where('status', CreditNoteReversalStatus::Issued->value)
            ->sum('total_cents');

        return max(0, $invoice->total_cents - $credited + $reversed);
    }
}
