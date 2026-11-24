<?php

namespace App\Domains\Invoicing\Services;

use App\Domains\Invoicing\Models\CreditNote;
use App\Domains\Invoicing\Models\CreditNoteApplication;
use App\Domains\Invoicing\Models\Invoice;
use App\Models\User;

class CreditNoteApplicationService
{
    public function __construct(
        private readonly CustomerAccountLedger $ledger,
        private readonly CreditNoteBalanceCalculator $creditNoteBalances,
        private readonly CreditNoteStatusCalculator $creditNoteStatuses,
        private readonly InvoiceBalanceCalculator $invoiceBalances,
    ) {}

    public function apply(CreditNote $creditNote, Invoice $invoice, int $amountCents, User $actor): CreditNoteApplication
    {
        $application = CreditNoteApplication::create([
            'credit_note_id' => $creditNote->id,
            'invoice_id' => $invoice->id,
            'amount_cents' => $amountCents,
            'applied_at' => now(),
            'created_by' => $actor->id,
        ]);

        $this->ledger->recordCreditApplied($application, $actor);
        $this->invoiceBalances->refreshStatus($invoice);
        $this->creditNoteStatuses->refresh($creditNote);

        return $application;
    }

    public function applyAvailableToOriginalInvoice(CreditNote $creditNote, User $actor): ?CreditNoteApplication
    {
        $invoice = $creditNote->invoice()->lockForUpdate()->firstOrFail();
        $available = $this->creditNoteBalances->availableCents($creditNote);
        $outstanding = $this->invoiceBalances->outstandingCents($invoice);
        $amount = min($available, $outstanding);

        if ($amount <= 0) {
            return null;
        }

        return $this->apply($creditNote, $invoice, $amount, $actor);
    }
}
