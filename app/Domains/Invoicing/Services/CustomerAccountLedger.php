<?php

namespace App\Domains\Invoicing\Services;

use App\Domains\Invoicing\Enums\CustomerAccountEntryType;
use App\Domains\Invoicing\Models\CustomerAccountEntry;
use App\Domains\Invoicing\Models\Invoice;
use App\Models\User;

class CustomerAccountLedger
{
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
        return CustomerAccountEntry::create([
            'billing_customer_id' => $invoice->billing_customer_id,
            'invoice_id' => $invoice->id,
            'type' => $type,
            'debit_cents' => $debitCents,
            'credit_cents' => $creditCents,
            'occurred_at' => now(),
            'created_by' => $actor->id,
            'metadata' => $metadata === [] ? null : $metadata,
        ]);
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
}
