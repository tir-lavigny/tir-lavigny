<?php

namespace App\Domains\Invoicing\Services;

use App\Domains\Invoicing\Enums\CreditNoteStatus;
use App\Domains\Invoicing\Models\CreditNote;
use App\Domains\Invoicing\Models\Invoice;

class CreditNoteCreditLimit
{
    public function remainingFor(Invoice $invoice): int
    {
        $credited = (int) CreditNote::query()
            ->where('invoice_id', $invoice->id)
            ->where('status', '!=', CreditNoteStatus::Draft->value)
            ->sum('total_cents');

        return max(0, $invoice->total_cents - $credited);
    }
}
