<?php

namespace App\Domains\Invoicing\Services;

use App\Domains\Invoicing\Enums\CustomerAccountEntryType;
use App\Domains\Invoicing\Models\BillingCustomer;
use App\Domains\Invoicing\Models\CreditNote;
use App\Domains\Invoicing\Models\CreditNoteApplication;
use App\Domains\Invoicing\Models\CreditNoteApplicationReversal;
use App\Domains\Invoicing\Models\CreditNoteRefund;
use App\Domains\Invoicing\Models\CreditNoteReversal;
use App\Domains\Invoicing\Models\CustomerAccountEntry;
use App\Domains\Invoicing\Models\Invoice;
use App\Models\User;

class CustomerAccountLedger
{
    /**
     * @param  array<string, mixed>  $metadata
     */
    public function recordEntry(
        BillingCustomer $billingCustomer,
        CustomerAccountEntryType $type,
        int $debitCents,
        int $creditCents,
        User $actor,
        ?Invoice $invoice = null,
        ?CreditNote $creditNote = null,
        array $metadata = [],
    ): CustomerAccountEntry {
        return CustomerAccountEntry::create([
            'billing_customer_id' => $billingCustomer->id,
            'invoice_id' => $invoice?->id,
            'credit_note_id' => $creditNote?->id,
            'type' => $type,
            'debit_cents' => $debitCents,
            'credit_cents' => $creditCents,
            'occurred_at' => now(),
            'created_by' => $actor->id,
            'metadata' => $metadata === [] ? null : $metadata,
        ]);
    }

    /**
     * @param  array<string, mixed>  $metadata
     */
    public function record(
        Invoice $invoice,
        CustomerAccountEntryType $type,
        int $debitCents,
        int $creditCents,
        User $actor,
        array $metadata = [],
    ): CustomerAccountEntry {
        return $this->recordEntry(
            $invoice->billingCustomer,
            $type,
            $debitCents,
            $creditCents,
            $actor,
            invoice: $invoice,
            metadata: $metadata,
        );
    }

    public function recordInvoiceIssued(Invoice $invoice, User $actor): CustomerAccountEntry
    {
        return $this->record(
            $invoice,
            CustomerAccountEntryType::InvoiceIssued,
            $invoice->total_cents,
            0,
            $actor,
        );
    }

    public function recordInvoiceCancelled(Invoice $invoice, User $actor): CustomerAccountEntry
    {
        return $this->record(
            $invoice,
            CustomerAccountEntryType::InvoiceCancelled,
            0,
            $this->invoiceOutstandingCents($invoice),
            $actor,
        );
    }

    public function recordCreditNoteIssued(CreditNote $creditNote, User $actor): CustomerAccountEntry
    {
        return $this->recordEntry(
            $creditNote->billingCustomer,
            CustomerAccountEntryType::CreditNoteIssued,
            0,
            $creditNote->total_cents,
            $actor,
            creditNote: $creditNote,
        );
    }

    public function recordCreditApplied(CreditNoteApplication $application, User $actor): void
    {
        $this->recordEntry(
            $application->creditNote->billingCustomer,
            CustomerAccountEntryType::CreditApplied,
            $application->amount_cents,
            0,
            $actor,
            creditNote: $application->creditNote,
            metadata: ['invoice_id' => $application->invoice_id],
        );

        $this->recordEntry(
            $application->invoice->billingCustomer,
            CustomerAccountEntryType::CreditApplied,
            0,
            $application->amount_cents,
            $actor,
            invoice: $application->invoice,
            metadata: ['credit_note_id' => $application->credit_note_id],
        );
    }

    public function recordCreditApplicationReversed(CreditNoteApplicationReversal $reversal, User $actor): void
    {
        $application = $reversal->application;

        $this->recordEntry(
            $application->creditNote->billingCustomer,
            CustomerAccountEntryType::CreditApplicationReversed,
            0,
            $reversal->amount_cents,
            $actor,
            creditNote: $application->creditNote,
            metadata: ['invoice_id' => $application->invoice_id],
        );

        $this->recordEntry(
            $application->invoice->billingCustomer,
            CustomerAccountEntryType::CreditApplicationReversed,
            $reversal->amount_cents,
            0,
            $actor,
            invoice: $application->invoice,
            metadata: ['credit_note_id' => $application->credit_note_id],
        );
    }

    public function recordCreditNoteRefunded(CreditNoteRefund $refund, User $actor): void
    {
        $this->recordEntry(
            $refund->creditNote->billingCustomer,
            CustomerAccountEntryType::CreditNoteRefunded,
            $refund->amount_cents,
            0,
            $actor,
            creditNote: $refund->creditNote,
        );
    }

    public function recordCreditNoteReversed(CreditNoteReversal $reversal, User $actor): void
    {
        $this->recordEntry(
            $reversal->creditNote->billingCustomer,
            CustomerAccountEntryType::CreditNoteReversed,
            $reversal->total_cents,
            0,
            $actor,
            creditNote: $reversal->creditNote,
        );
    }

    public function invoiceOutstandingCents(Invoice $invoice): int
    {
        $debits = (int) CustomerAccountEntry::query()
            ->where('invoice_id', $invoice->id)
            ->sum('debit_cents');
        $credits = (int) CustomerAccountEntry::query()
            ->where('invoice_id', $invoice->id)
            ->sum('credit_cents');

        if ($debits === 0 && $credits === 0) {
            return $invoice->total_cents;
        }

        return max(0, $debits - $credits);
    }

    public function availableCreditCents(CreditNote $creditNote): int
    {
        $credits = (int) CustomerAccountEntry::query()
            ->where('credit_note_id', $creditNote->id)
            ->sum('credit_cents');
        $debits = (int) CustomerAccountEntry::query()
            ->where('credit_note_id', $creditNote->id)
            ->sum('debit_cents');

        return max(0, $credits - $debits);
    }

    public function customerAvailableCreditCents(BillingCustomer $billingCustomer): int
    {
        $credits = (int) CustomerAccountEntry::query()
            ->where('billing_customer_id', $billingCustomer->id)
            ->whereNotNull('credit_note_id')
            ->sum('credit_cents');
        $debits = (int) CustomerAccountEntry::query()
            ->where('billing_customer_id', $billingCustomer->id)
            ->whereNotNull('credit_note_id')
            ->sum('debit_cents');

        return max(0, $credits - $debits);
    }
}
