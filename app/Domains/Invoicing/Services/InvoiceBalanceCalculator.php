<?php

namespace App\Domains\Invoicing\Services;

use App\Domains\Invoicing\Enums\InvoiceStatus;
use App\Domains\Invoicing\Models\CreditNoteApplication;
use App\Domains\Invoicing\Models\CreditNoteApplicationReversal;
use App\Domains\Invoicing\Models\Invoice;
use App\Domains\Invoicing\Models\InvoicePayment;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;

class InvoiceBalanceCalculator
{
    /**
     * @param  Builder<Invoice>  $query
     * @return Builder<Invoice>
     */
    public function withBalanceSums(Builder $query): Builder
    {
        return $query
            ->withSum(['paymentAllocations as paid_cents' => fn (Builder $query) => $query->whereNull('reversed_at')], 'amount_cents')
            ->withSum('creditNoteApplications as credited_cents', 'amount_cents')
            ->withSum('creditNoteApplicationReversals as credited_reversed_cents', 'amount_cents');
    }

    public function applyBalanceAttributes(Invoice $invoice): Invoice
    {
        $credited = max(
            0,
            (int) ($invoice->getAttribute('credited_cents') ?? 0)
                - (int) ($invoice->getAttribute('credited_reversed_cents') ?? 0),
        );
        $paid = (int) ($invoice->getAttribute('paid_cents') ?? 0);
        $remaining = max(0, $invoice->total_cents - $paid - $credited);

        $invoice->setAttribute('paid_cents', $paid);
        $invoice->setAttribute('credited_cents', $credited);
        $invoice->setAttribute('remaining_cents', $remaining);
        $invoice->setAttribute('days_overdue', $this->daysOverdue($invoice, outstandingCents: $remaining));

        return $invoice;
    }

    public function creditedCents(Invoice $invoice): int
    {
        $applied = (int) CreditNoteApplication::query()
            ->where('invoice_id', $invoice->id)
            ->sum('amount_cents');

        $reversed = (int) CreditNoteApplicationReversal::query()
            ->whereHas('application', fn ($query) => $query->where('invoice_id', $invoice->id))
            ->sum('amount_cents');

        return max(0, $applied - $reversed);
    }

    public function paidCents(Invoice $invoice): int
    {
        return (int) InvoicePayment::query()
            ->where('invoice_id', $invoice->id)
            ->whereNull('reversed_at')
            ->sum('amount_cents');
    }

    public function outstandingCents(Invoice $invoice): int
    {
        return max(0, $invoice->total_cents - $this->paidCents($invoice) - $this->creditedCents($invoice));
    }

    public function daysOverdue(Invoice $invoice, ?CarbonInterface $asOf = null, ?int $outstandingCents = null): int
    {
        $outstandingCents ??= $this->outstandingCents($invoice);

        if ($invoice->due_date === null || ! $invoice->isDistributable() || $outstandingCents <= 0) {
            return 0;
        }

        $asOf ??= now();
        $dueDate = $invoice->due_date->copy()->startOfDay();
        $today = $asOf->copy()->startOfDay();

        if ($dueDate->greaterThanOrEqualTo($today)) {
            return 0;
        }

        return (int) $dueDate->diffInDays($today);
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
