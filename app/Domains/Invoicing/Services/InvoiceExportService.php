<?php

namespace App\Domains\Invoicing\Services;

use App\Domains\Invoicing\Models\Invoice;
use App\Support\Money;
use Illuminate\Database\Eloquent\Builder;

class InvoiceExportService
{
    public function __construct(
        private readonly InvoiceListQuery $invoices,
    ) {}

    /**
     * @param  array{search?: string|null, status?: string|null, type?: string|null, year?: int|null, overdue?: bool}  $filters
     * @return Builder<Invoice>
     */
    public function query(array $filters): Builder
    {
        return $this->invoices->filtered($filters)
            ->orderByDesc('issue_date')
            ->orderByDesc('id');
    }

    /**
     * @return array<int, string>
     */
    public function headers(): array
    {
        return [
            'ID',
            'Numéro',
            'Type',
            'Statut',
            'Client',
            'Email',
            'Date d’émission',
            'Échéance',
            'Total CHF',
            'Payé CHF',
            'Crédité CHF',
            'Reste CHF',
            'Jours de retard',
            'Mode d’envoi',
        ];
    }

    /**
     * @param  Builder<Invoice>  $query
     * @return iterable<int, array<int, int|string>>
     */
    public function rows(Builder $query): iterable
    {
        foreach ($query->cursor() as $invoice) {
            $invoice = $this->invoices->applyBalanceAttributes($invoice);

            yield [
                $invoice->id,
                $invoice->invoice_number ?? '',
                $invoice->type->label(),
                $invoice->status->label(),
                $invoice->recipient_name,
                $invoice->recipient_email ?? '',
                $invoice->issue_date?->format('d.m.Y') ?? '',
                $invoice->due_date?->format('d.m.Y') ?? '',
                Money::fromCents($invoice->total_cents) ?? '0.00',
                Money::fromCents((int) $invoice->getAttribute('paid_cents')) ?? '0.00',
                Money::fromCents((int) $invoice->getAttribute('credited_cents')) ?? '0.00',
                Money::fromCents((int) $invoice->getAttribute('remaining_cents')) ?? '0.00',
                (int) $invoice->getAttribute('days_overdue'),
                $invoice->delivery_method->label(),
            ];
        }
    }

    /**
     * @return array<int, float>
     */
    public function widths(): array
    {
        return [
            1 => 8,
            2 => 16,
            3 => 18,
            4 => 18,
            5 => 28,
            6 => 30,
            7 => 14,
            8 => 14,
            9 => 14,
            10 => 14,
            11 => 14,
            12 => 14,
            13 => 14,
            14 => 14,
        ];
    }
}
