<?php

namespace App\Domains\Invoicing\Services;

use App\Domains\Invoicing\Models\Payment;
use App\Support\Money;
use Illuminate\Database\Eloquent\Builder;

class PaymentExportService
{
    public function __construct(
        private readonly PaymentListQuery $payments,
    ) {}

    /**
     * @param  array{search?: string|null, method?: string|null, status?: string|null, allocation_status?: string|null, year?: int|null}  $filters
     * @return Builder<Payment>
     */
    public function query(array $filters): Builder
    {
        return $this->payments->filtered($filters)
            ->with('allocations.invoice')
            ->orderByDesc('payment_date')
            ->orderByDesc('id');
    }

    /**
     * @return array<int, string>
     */
    public function headers(): array
    {
        return [
            'ID',
            'Date',
            'Moyen',
            'Référence',
            'Notes',
            'Montant CHF',
            'Alloué CHF',
            'Non alloué CHF',
            'Statut',
            'Enregistré par',
            'Factures',
            'Créé le',
        ];
    }

    /**
     * @param  Builder<Payment>  $query
     * @return iterable<int, array<int, int|string>>
     */
    public function rows(Builder $query): iterable
    {
        foreach ($query->cursor() as $payment) {
            $payment = $this->payments->applyAmounts($payment);
            $invoiceNumbers = $payment->allocations
                ->map(fn ($allocation): string => (string) $allocation->invoice?->invoice_number)
                ->filter()
                ->unique()
                ->implode(', ');

            yield [
                $payment->id,
                $payment->payment_date?->format('d.m.Y') ?? '',
                $payment->method->label(),
                $payment->reference ?? '',
                $payment->notes ?? '',
                Money::fromCents($payment->amount_cents) ?? '0.00',
                Money::fromCents((int) $payment->getAttribute('allocated_cents')) ?? '0.00',
                Money::fromCents((int) $payment->getAttribute('unallocated_cents')) ?? '0.00',
                $payment->isVoided() ? 'Annulé' : 'Actif',
                optional($payment->creator)->name ?? '',
                $invoiceNumbers,
                $payment->created_at?->format('d.m.Y H:i') ?? '',
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
            2 => 14,
            3 => 18,
            4 => 24,
            5 => 36,
            6 => 14,
            7 => 14,
            8 => 14,
            9 => 12,
            10 => 22,
            11 => 32,
            12 => 18,
        ];
    }
}
