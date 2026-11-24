<?php

namespace App\Domains\Invoicing\Services;

use App\Domains\Invoicing\Enums\InvoiceStatus;
use App\Domains\Invoicing\Models\CreditNoteApplication;
use App\Domains\Invoicing\Models\Invoice;
use App\Domains\Invoicing\Models\InvoicePayment;

class InvoiceBalanceCalculator
{
    public function creditedCents(Invoice $invoice): int
    {
        return (int) CreditNoteApplication::query()
            ->where('invoice_id', $invoice->id)
            ->sum('amount_cents');
    }

    public function paidCents(Invoice $invoice): int
    {
        return (int) InvoicePayment::query()
            ->where('invoice_id', $invoice->id)
            ->sum('amount_cents');
    }

    public function outstandingCents(Invoice $invoice): int
    {
        return max(0, $invoice->total_cents - $this->paidCents($invoice) - $this->creditedCents($invoice));
    }

    public function refreshStatus(Invoice $invoice): Invoice
    {
        if ($invoice->status === InvoiceStatus::Draft || $invoice->status === InvoiceStatus::Cancelled) {
            return $invoice;
        }

        $settled = $this->paidCents($invoice) + $this->creditedCents($invoice);
        $outstanding = $this->outstandingCents($invoice);

        $status = match (true) {
            $outstanding <= 0 => InvoiceStatus::Paid,
            $settled > 0 => InvoiceStatus::PartiallyPaid,
            default => InvoiceStatus::Issued,
        };

        if ($invoice->status !== $status) {
            $invoice->update(['status' => $status]);
        }

        return $invoice->refresh();
    }
}
