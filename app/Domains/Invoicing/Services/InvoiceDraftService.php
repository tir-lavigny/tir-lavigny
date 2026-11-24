<?php

namespace App\Domains\Invoicing\Services;

use App\Domains\Invoicing\Models\BillingCustomer;
use App\Domains\Invoicing\Models\Invoice;
use App\Domains\Invoicing\Models\InvoiceItem;
use App\Support\Money;

class InvoiceDraftService
{
    public function __construct(
        private readonly InvoiceTotals $totals,
    ) {}

    /**
     * @param  array<int, array<string, mixed>>  $items
     * @return array{
     *     lines: array<int, array<string, mixed>>,
     *     subtotal_cents: int,
     *     discounts_cents: int,
     *     total_cents: int
     * }
     */
    public function prepareItems(array $items): array
    {
        $normalized = [];

        foreach (array_values($items) as $item) {
            $unitPriceCents = array_key_exists('unit_price_cents', $item)
                ? (int) $item['unit_price_cents']
                : (Money::toCents($item['unit_price'] ?? null) ?? 0);

            $normalized[] = [
                'type' => $item['type'],
                'description' => $item['description'],
                'quantity' => (string) $item['quantity'],
                'unit_price_cents' => $unitPriceCents,
                'product_id' => $item['product_id'] ?? null,
                'metadata' => $item['metadata'] ?? null,
            ];
        }

        return $this->totals->calculate($normalized);
    }

    /**
     * @param  array<int, array<string, mixed>>  $lines
     */
    public function createItems(Invoice $invoice, array $lines): void
    {
        foreach ($lines as $line) {
            InvoiceItem::create([
                'invoice_id' => $invoice->id,
                'product_id' => $line['product_id'],
                'type' => $line['type'],
                'description' => $line['description'],
                'quantity' => $line['quantity'],
                'unit_price_cents' => $line['unit_price_cents'],
                'total_cents' => $line['total_cents'],
                'position' => $line['position'],
                'metadata' => $line['metadata'],
            ]);
        }
    }

    /**
     * @param  array<int, array<string, mixed>>  $lines
     */
    public function replaceItems(Invoice $invoice, array $lines): void
    {
        $invoice->items()->delete();

        $this->createItems($invoice, $lines);
    }

    /**
     * @return array<string, mixed>
     */
    public function recipientSnapshot(BillingCustomer $billingCustomer): array
    {
        return [
            'recipient_name' => $billingCustomer->displayName(),
            'recipient_address' => $billingCustomer->address,
            'recipient_postal_code' => $billingCustomer->postal_code,
            'recipient_city' => $billingCustomer->city,
            'recipient_country' => $billingCustomer->country,
            'recipient_email' => $billingCustomer->email,
        ];
    }
}
