<?php

namespace App\Domains\Invoicing\Services;

use App\Domains\Invoicing\Enums\DeliveryStatus;
use App\Domains\Invoicing\Enums\InvoiceDeliveryMethod;
use App\Domains\Invoicing\Enums\InvoiceStatus;
use App\Domains\Invoicing\Models\Invoice;
use App\Domains\Invoicing\Models\InvoiceReminder;

class InvoicingDashboardService
{
    public function __construct(
        private readonly InvoiceListQuery $invoices,
        private readonly InvoiceDeliveryQueue $deliveryQueue,
    ) {}

    /**
     * @return array{
     *     year: int,
     *     issued_count: int,
     *     issued_total_cents: int,
     *     paid_cents: int,
     *     credited_cents: int,
     *     outstanding_cents: int,
     *     overdue_count: int,
     *     overdue_cents: int,
     *     awaiting_payment_count: int,
     *     awaiting_email_count: int,
     *     awaiting_postal_count: int,
     *     reminders_count: int,
     *     status_counts: array<int, array{value: string, label: string, count: int}>
     * }
     */
    public function summary(?int $year = null): array
    {
        $year ??= (int) now()->format('Y');

        $invoices = $this->invoices
            ->filtered(['year' => $year])
            ->get()
            ->map(fn (Invoice $invoice): Invoice => $this->invoices->applyBalanceAttributes($invoice));

        $settled = $invoices->whereIn('status', [
            InvoiceStatus::Issued,
            InvoiceStatus::PartiallyPaid,
            InvoiceStatus::Paid,
        ]);
        $outstanding = $settled->whereIn('status', [
            InvoiceStatus::Issued,
            InvoiceStatus::PartiallyPaid,
        ])->filter(fn (Invoice $invoice): bool => (int) $invoice->getAttribute('remaining_cents') > 0);
        $overdue = $outstanding->filter(fn (Invoice $invoice): bool => (int) $invoice->getAttribute('days_overdue') > 0);

        return [
            'year' => $year,
            'issued_count' => $settled->count(),
            'issued_total_cents' => (int) $settled->sum('total_cents'),
            'paid_cents' => (int) $settled->sum(fn (Invoice $invoice): int => (int) $invoice->getAttribute('paid_cents')),
            'credited_cents' => (int) $settled->sum(fn (Invoice $invoice): int => (int) $invoice->getAttribute('credited_cents')),
            'outstanding_cents' => (int) $outstanding->sum(fn (Invoice $invoice): int => (int) $invoice->getAttribute('remaining_cents')),
            'overdue_count' => $overdue->count(),
            'overdue_cents' => (int) $overdue->sum(fn (Invoice $invoice): int => (int) $invoice->getAttribute('remaining_cents')),
            'awaiting_payment_count' => $outstanding->count(),
            'awaiting_email_count' => $this->deliveryQueue
                ->pending(InvoiceDeliveryMethod::Email)
                ->whereYear('issue_date', $year)
                ->count(),
            'awaiting_postal_count' => $this->deliveryQueue
                ->pending(InvoiceDeliveryMethod::Postal)
                ->whereYear('issue_date', $year)
                ->count(),
            'reminders_count' => InvoiceReminder::query()
                ->whereIn('status', [DeliveryStatus::Sent, DeliveryStatus::Printed])
                ->whereYear('processed_at', $year)
                ->count(),
            'status_counts' => array_map(
                fn (InvoiceStatus $status): array => [
                    'value' => $status->value,
                    'label' => $status->label(),
                    'count' => $invoices->where('status', $status)->count(),
                ],
                InvoiceStatus::cases(),
            ),
        ];
    }
}
