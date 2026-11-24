<?php

namespace App\Domains\Invoicing\Services;

use App\Domains\Invoicing\Enums\InvoiceItemType;
use App\Support\Money;

class InvoiceTotals
{
    /**
     * @param  array<int, array<string, mixed>>  $lines
     * @return array{
     *     lines: array<int, array<string, mixed>>,
     *     subtotal_cents: int,
     *     discounts_cents: int,
     *     total_cents: int
     * }
     */
    public function calculate(array $lines): array
    {
        $calculated = [];
        $subtotalCents = 0;
        $discountsCents = 0;

        foreach (array_values($lines) as $index => $line) {
            $type = $line['type'] instanceof InvoiceItemType
                ? $line['type']
                : InvoiceItemType::from((string) $line['type']);

            $unitPriceCents = (int) ($line['unit_price_cents'] ?? 0);
            $totalCents = Money::multiplyByQuantity($unitPriceCents, $line['quantity'] ?? 0);

            if ($type === InvoiceItemType::Discount) {
                $discountsCents += $totalCents;
            } else {
                $subtotalCents += $totalCents;
            }

            $calculated[] = [
                ...$line,
                'type' => $type,
                'position' => $index + 1,
                'unit_price_cents' => $unitPriceCents,
                'total_cents' => $totalCents,
            ];
        }

        return [
            'lines' => $calculated,
            'subtotal_cents' => $subtotalCents,
            'discounts_cents' => $discountsCents,
            'total_cents' => $subtotalCents + $discountsCents,
        ];
    }
}
