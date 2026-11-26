<?php

namespace App\Domains\Invoicing\Services;

use App\Domains\Invoicing\Enums\PaymentMethod;
use App\Domains\Invoicing\Models\Payment;
use Illuminate\Database\Eloquent\Builder;

class PaymentListQuery
{
    /**
     * @param  array{search?: string|null, method?: string|null, status?: string|null, allocation_status?: string|null, year?: int|null}  $filters
     * @return Builder<Payment>
     */
    public function filtered(array $filters = []): Builder
    {
        $query = Payment::query()
            ->with('creator')
            ->withSum(['allocations as allocated_cents' => fn (Builder $query) => $query->whereNull('reversed_at')], 'amount_cents');

        $method = $filters['method'] ?? null;

        if (is_string($method) && in_array($method, array_column(PaymentMethod::cases(), 'value'), true)) {
            $query->where('method', $method);
        }

        $status = $filters['status'] ?? null;

        if ($status === 'voided') {
            $query->whereNotNull('voided_at');
        } elseif ($status === 'active') {
            $query->whereNull('voided_at');
        }

        $allocationStatus = $filters['allocation_status'] ?? null;

        if (is_string($allocationStatus) && in_array($allocationStatus, ['unallocated', 'partially_allocated', 'fully_allocated'], true)) {
            $query->whereNull('voided_at')->allocationStatus($allocationStatus);
        }

        $year = $filters['year'] ?? null;

        if (is_int($year)) {
            $query->whereYear('payment_date', $year);
        }

        $search = $filters['search'] ?? null;

        if (is_string($search) && filled($search)) {
            $query->where(function (Builder $query) use ($search): void {
                $query->where('reference', 'like', "%{$search}%")
                    ->orWhere('notes', 'like', "%{$search}%");
            });
        }

        return $query;
    }

    public function applyAmounts(Payment $payment): Payment
    {
        $allocated = (int) ($payment->getAttribute('allocated_cents') ?? 0);

        $payment->setAttribute('allocated_cents', $allocated);
        $payment->setAttribute(
            'unallocated_cents',
            $payment->isVoided() ? 0 : max(0, $payment->amount_cents - $allocated),
        );
        $payment->setAttribute('is_voided', $payment->isVoided());

        return $payment;
    }
}
