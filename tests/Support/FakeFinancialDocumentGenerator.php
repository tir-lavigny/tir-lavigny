<?php

namespace Tests\Support;

use App\Domains\Invoicing\Models\CreditNote;
use App\Domains\Invoicing\Models\CreditNoteReversal;
use App\Domains\Invoicing\Models\Invoice;
use App\Domains\Invoicing\Services\FinancialDocumentGenerator;

class FakeFinancialDocumentGenerator implements FinancialDocumentGenerator
{
    public function invoice(Invoice $invoice): string
    {
        return $this->pdf('invoice', $invoice->invoice_number ?? (string) $invoice->id);
    }

    public function creditNote(CreditNote $creditNote): string
    {
        return $this->pdf('credit-note', $creditNote->credit_note_number ?? (string) $creditNote->id);
    }

    public function creditNoteReversal(CreditNoteReversal $reversal): string
    {
        return $this->pdf('credit-note-reversal', $reversal->credit_note_reversal_number ?? (string) $reversal->id);
    }

    private function pdf(string $type, string $identifier): string
    {
        return "%PDF-1.4\n% {$type} {$identifier}\n%%EOF\n";
    }
}
