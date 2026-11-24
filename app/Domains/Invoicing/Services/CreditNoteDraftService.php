<?php

namespace App\Domains\Invoicing\Services;

use App\Domains\Invoicing\Models\CreditNote;
use App\Domains\Invoicing\Models\CreditNoteItem;
use App\Domains\Invoicing\Models\Invoice;
use App\Support\Money;

class CreditNoteDraftService
{
    public function __construct(
        private readonly CreditNoteTotals $totals,
    ) {}

    /**
     * @param  array<int, array<string, mixed>>  $items
     * @return array{
     *     lines: array<int, array<string, mixed>>,
     *     subtotal_cents: int,
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
                'invoice_item_id' => $item['invoice_item_id'] ?? null,
                'description' => $item['description'],
                'quantity' => (string) $item['quantity'],
                'unit_price_cents' => $unitPriceCents,
                'metadata' => $item['metadata'] ?? null,
            ];
        }

        return $this->totals->calculate($normalized);
    }

    /**
     * @param  array<int, array<string, mixed>>  $lines
     */
    public function createItems(CreditNote $creditNote, array $lines): void
    {
        foreach ($lines as $line) {
            CreditNoteItem::create([
                'credit_note_id' => $creditNote->id,
                'invoice_item_id' => $line['invoice_item_id'],
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
    public function replaceItems(CreditNote $creditNote, array $lines): void
    {
        $creditNote->items()->delete();

        $this->createItems($creditNote, $lines);
    }

    /**
     * @return array<string, mixed>
     */
    public function snapshot(Invoice $invoice): array
    {
        return [
            'billing_customer_id' => $invoice->billing_customer_id,
            'recipient_name' => $invoice->recipient_name,
            'recipient_address' => $invoice->recipient_address,
            'recipient_postal_code' => $invoice->recipient_postal_code,
            'recipient_city' => $invoice->recipient_city,
            'recipient_country' => $invoice->recipient_country,
            'recipient_email' => $invoice->recipient_email,
            'creditor_name' => $invoice->creditor_name,
            'creditor_address' => $invoice->creditor_address,
            'creditor_postal_code' => $invoice->creditor_postal_code,
            'creditor_city' => $invoice->creditor_city,
            'creditor_country' => $invoice->creditor_country,
            'creditor_iban' => $invoice->creditor_iban,
        ];
    }
}
