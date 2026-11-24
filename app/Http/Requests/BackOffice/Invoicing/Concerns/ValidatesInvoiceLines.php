<?php

namespace App\Http\Requests\BackOffice\Invoicing\Concerns;

use App\Domains\Invoicing\Enums\InvoiceItemType;
use App\Domains\Invoicing\Services\InvoiceTotals;
use App\Support\Money;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

trait ValidatesInvoiceLines
{
    /**
     * @return array<string, mixed>
     */
    protected function invoiceLineRules(): array
    {
        return [
            'items' => ['required', 'array', 'min:1'],
            'items.*.type' => ['required', Rule::enum(InvoiceItemType::class)],
            'items.*.description' => ['required', 'string', 'max:500'],
            'items.*.quantity' => ['required', 'numeric', 'gt:0'],
            'items.*.unit_price' => ['required', 'numeric'],
            'items.*.product_id' => ['nullable', 'integer', Rule::exists('products', 'id')],
            'items.*.metadata' => ['nullable', 'array'],
        ];
    }

    protected function validateInvoiceTotals(Validator $validator): void
    {
        if ($validator->errors()->has('items')) {
            return;
        }

        $items = collect((array) $this->input('items', []))->values();
        $normalized = [];
        $hasNonDiscountLine = false;

        foreach ($items as $index => $item) {
            $type = InvoiceItemType::tryFrom((string) ($item['type'] ?? ''));
            $unitPriceCents = Money::toCents($item['unit_price'] ?? null);

            if ($type === null || $unitPriceCents === null) {
                continue;
            }

            if (in_array($type, [InvoiceItemType::Product, InvoiceItemType::Fee], true) && $unitPriceCents < 0) {
                $validator->errors()->add("items.{$index}.unit_price", 'Le prix unitaire ne peut pas être négatif.');
            }

            if ($type === InvoiceItemType::Discount && $unitPriceCents > 0) {
                $validator->errors()->add("items.{$index}.unit_price", 'Une remise doit avoir un montant négatif.');
            }

            if ($type !== InvoiceItemType::Discount) {
                $hasNonDiscountLine = true;
            }

            $normalized[] = [
                'type' => $type,
                'quantity' => $item['quantity'] ?? 0,
                'unit_price_cents' => $unitPriceCents,
            ];
        }

        if (! $hasNonDiscountLine) {
            $validator->errors()->add('items', 'La facture doit contenir au moins une ligne non-discount.');

            return;
        }

        $totals = app(InvoiceTotals::class)->calculate($normalized);

        if ($totals['subtotal_cents'] <= 0) {
            $validator->errors()->add('items', 'Le sous-total de la facture doit être positif.');
        }

        if ($totals['total_cents'] < 0) {
            $validator->errors()->add('items', 'Le total de la facture ne peut pas être négatif.');
        }
    }
}
