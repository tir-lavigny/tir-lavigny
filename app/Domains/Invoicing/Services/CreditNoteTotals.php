<?php

namespace App\Domains\Invoicing\Services;

use App\Support\Money;

class CreditNoteTotals
{
    /**
     * @param  array<int, array<string, mixed>>  $lines
     * @return array{
     *     lines: array<int, array<string, mixed>>,
     *     subtotal_cents: int,
     *     total_cents: int
     * }
     */
    public function calculate(array $lines): array
    {
        $calculated = [];
        $totalCents = 0;

        foreach (array_values($lines) as $index => $line) {
            $unitPriceCents = (int) ($line['unit_price_cents'] ?? 0);
            $totalCents += Money::multiplyByQuantity($unitPriceCents, $line['quantity'] ?? 0);

            $calculated[] = [
                ...$line,
                'position' => $index + 1,
                'unit_price_cents' => $unitPriceCents,
                'total_cents' => Money::multiplyByQuantity($unitPriceCents, $line['quantity'] ?? 0),
            ];
        }

        return [
            'lines' => $calculated,
            'subtotal_cents' => array_sum(array_column($calculated, 'total_cents')),
            'total_cents' => array_sum(array_column($calculated, 'total_cents')),
        ];
    }
}
