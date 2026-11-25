<?php

namespace App\Domains\Invoicing\Services;

use App\Domains\Invoicing\Enums\InvoiceStatus;
use App\Domains\Invoicing\Enums\InvoiceType;
use App\Domains\Invoicing\Models\Invoice;
use App\Traits\SearchesAccentInsensitively;
use Illuminate\Database\Eloquent\Builder;

class InvoiceListQuery
{
    use SearchesAccentInsensitively;

    public function __construct(
        private readonly InvoiceBalanceCalculator $balances,
    ) {}

    /**
     * @param  array{search?: string|null, status?: string|null, type?: string|null, year?: int|null, overdue?: bool}  $filters
     * @return Builder<Invoice>
     */
    public function filtered(array $filters = []): Builder
    {
        $query = $this->balances->withBalanceSums(
            Invoice::query()->with('billingCustomer'),
        );

        $status = $filters['status'] ?? null;

        if (is_string($status) && in_array($status, array_column(InvoiceStatus::cases(), 'value'), true)) {
            $query->where('status', $status);
        }

        $type = $filters['type'] ?? null;

        if (is_string($type) && in_array($type, array_column(InvoiceType::cases(), 'value'), true)) {
            $query->where('type', $type);
        }

        if (($filters['overdue'] ?? false) === true) {
            $this->applyOverdueConditions($query);
        }

        $year = $filters['year'] ?? null;

        if (is_int($year)) {
            $query->where(function (Builder $query) use ($year): void {
                $query->whereYear('issue_date', $year)
                    ->orWhere(function (Builder $query) use ($year): void {
                        $query->whereNull('issue_date')->whereYear('created_at', $year);
                    });
            });
        }

        $search = $filters['search'] ?? null;

        if (is_string($search) && filled($search)) {
            $query->where(function (Builder $query) use ($search): void {
                $this->whereAccentInsensitiveLike($query, ['recipient_name'], $search);
                $query->orWhere('invoice_number', 'like', "%{$search}%");
            });
        }

        return $query;
    }

    /**
     * @param  array{search?: string|null, year?: int|null}  $filters
     * @return Builder<Invoice>
     */
    public function overdue(array $filters = []): Builder
    {
        return $this->filtered([...$filters, 'overdue' => true]);
    }

    public function applyBalanceAttributes(Invoice $invoice): Invoice
    {
        return $this->balances->applyBalanceAttributes($invoice);
    }

    /**
     * @param  Builder<Invoice>  $query
     */
    private function applyOverdueConditions(Builder $query): void
    {
        $query->whereIn('status', [
            InvoiceStatus::Issued,
            InvoiceStatus::PartiallyPaid,
        ])
            ->whereNotNull('due_date')
            ->whereDate('due_date', '<', today()->toDateString());
    }
}
