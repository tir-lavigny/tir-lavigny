<?php

namespace App\Domains\Invoicing\Actions\CreditNotes;

use App\Domains\Invoicing\Enums\InvoiceStatus;
use App\Domains\Invoicing\Exceptions\CreditNoteNotApplicable;
use App\Domains\Invoicing\Models\CreditNote;
use App\Domains\Invoicing\Models\CreditNoteApplication;
use App\Domains\Invoicing\Models\Invoice;
use App\Domains\Invoicing\Services\CreditNoteApplicationService;
use App\Domains\Invoicing\Services\CreditNoteBalanceCalculator;
use App\Domains\Invoicing\Services\InvoiceBalanceCalculator;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class ApplyCreditNoteToInvoice
{
    public function __construct(
        private readonly CreditNoteApplicationService $applications,
        private readonly CreditNoteBalanceCalculator $creditNoteBalances,
        private readonly InvoiceBalanceCalculator $invoiceBalances,
    ) {}

    public function execute(CreditNote $creditNote, Invoice $invoice, int $amountCents, User $actor): CreditNoteApplication
    {
        return DB::transaction(function () use ($creditNote, $invoice, $amountCents, $actor): CreditNoteApplication {
            $lockedNote = CreditNote::query()->lockForUpdate()->findOrFail($creditNote->id);
            $lockedInvoice = Invoice::query()->lockForUpdate()->findOrFail($invoice->id);

            if (! $lockedNote->isIssued()) {
                throw CreditNoteNotApplicable::for($lockedNote, 'the credit note is not issued.');
            }

            if ($lockedNote->billing_customer_id !== $lockedInvoice->billing_customer_id) {
                throw CreditNoteNotApplicable::for($lockedNote, 'the invoices belong to different customers.');
            }

            if (in_array($lockedInvoice->status, [InvoiceStatus::Draft, InvoiceStatus::Cancelled], true)) {
                throw CreditNoteNotApplicable::for($lockedNote, 'the target invoice cannot receive credit.');
            }

            if ($amountCents <= 0) {
                throw CreditNoteNotApplicable::for($lockedNote, 'the amount must be positive.');
            }

            if ($amountCents > $this->creditNoteBalances->availableCents($lockedNote)) {
                throw CreditNoteNotApplicable::for($lockedNote, 'the amount exceeds the available credit.');
            }

            if ($amountCents > $this->invoiceBalances->outstandingCents($lockedInvoice)) {
                throw CreditNoteNotApplicable::for($lockedNote, 'the amount exceeds the invoice outstanding balance.');
            }

            return $this->applications->apply($lockedNote, $lockedInvoice, $amountCents, $actor);
        });
    }
}
