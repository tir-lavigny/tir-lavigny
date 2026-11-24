<?php

namespace App\Domains\Invoicing\Services;

use App\Domains\Invoicing\Models\CreditNote;
use App\Domains\Invoicing\Models\CreditNoteReversal;
use App\Domains\Invoicing\Models\Invoice;

interface FinancialDocumentGenerator
{
    public function invoice(Invoice $invoice): string;

    public function creditNote(CreditNote $creditNote): string;

    public function creditNoteReversal(CreditNoteReversal $reversal): string;
}
