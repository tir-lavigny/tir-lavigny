<?php

namespace App\Services;

use App\Models\Product;
use App\Models\Voucher;
use App\Support\Money;

class TicketCalculator
{
    /**
     * Compute ticket total and line items from request data.
     *
     * Each voucher discounts exactly one unit of its product. A request line
     * with vouchers expands into one computed row per voucher (quantity 1)
     * plus one remainder row without voucher.
     *
     * Amounts are computed in integer cents and converted to decimals only at
     * the boundary, for the POS preview payload.
     *
     * @param  array<int, array{product_id: int, quantity: int, custom_unit_price?: float, voucher_ids?: array<int, int|null>}>  $items
     * @return array{total: float, items: array<int, array{product_id: int, voucher_id: int|null, quantity: int, unit_price: float, subtotal: float}>}
     */
    public function compute(array $items): array
    {
        $result = $this->computeCents($items);

        return [
            'total' => Money::toFloat($result['total_cents']),
            'items' => array_map(fn (array $item): array => [
                'product_id' => $item['product_id'],
                'voucher_id' => $item['voucher_id'],
                'quantity' => $item['quantity'],
                'unit_price' => Money::toFloat($item['unit_price_cents']),
                'subtotal' => Money::toFloat($item['subtotal_cents']),
            ], $result['items']),
        ];
    }

    /**
     * Same computation as compute(), but returning integer cents so that the
     * stored ticket never passes through a float.
     *
     * @param  array<int, array{product_id: int, quantity: int, custom_unit_price?: float, voucher_ids?: array<int, int|null>}>  $items
     * @return array{total_cents: int, items: list<array{product_id: int, voucher_id: int|null, quantity: int, unit_price_cents: int, subtotal_cents: int}>}
     */
    public function computeCents(array $items): array
    {
        $computedItems = [];
        $totalCents = 0;

        foreach ($items as $item) {
            $product = Product::findOrFail($item['product_id']);
            $unitPriceCents = $this->customPriceCents($item['custom_unit_price'] ?? null)
                ?? (int) $product->unit_price_cents;
            $quantity = (int) $item['quantity'];

            $voucherIds = array_values(array_filter(
                $item['voucher_ids'] ?? [],
                fn ($voucherId) => ! empty($voucherId)
            ));

            $vouchers = [];
            foreach ($voucherIds as $voucherId) {
                $vouchers[] = Voucher::findOrFail($voucherId);
            }

            foreach ($vouchers as $voucher) {
                $subtotalCents = $unitPriceCents - $this->unitDiscountCents($voucher, $unitPriceCents);
                $totalCents += $subtotalCents;

                $computedItems[] = [
                    'product_id' => $product->id,
                    'voucher_id' => (int) $voucher->id,
                    'quantity' => 1,
                    'unit_price_cents' => $unitPriceCents,
                    'subtotal_cents' => $subtotalCents,
                ];
            }

            $remaining = $quantity - count($vouchers);

            if ($remaining > 0) {
                $subtotalCents = $unitPriceCents * $remaining;
                $totalCents += $subtotalCents;

                $computedItems[] = [
                    'product_id' => $product->id,
                    'voucher_id' => null,
                    'quantity' => $remaining,
                    'unit_price_cents' => $unitPriceCents,
                    'subtotal_cents' => $subtotalCents,
                ];
            }
        }

        return [
            'total_cents' => $totalCents,
            'items' => $computedItems,
        ];
    }

    private function customPriceCents(float|int|string|null $customPrice): ?int
    {
        if ($customPrice === null || $customPrice === '') {
            return null;
        }

        return Money::toCents($customPrice);
    }

    /**
     * Discount one voucher applies to one unit, never more than the unit price.
     * A zero percent is treated as absent so fixed vouchers stored with a
     * legacy 0 percent still apply their fixed amount.
     */
    private function unitDiscountCents(Voucher $voucher, int $unitPriceCents): int
    {
        $percent = $voucher->discount_percent !== null
            ? (float) $voucher->discount_percent
            : null;

        if ($percent !== null && $percent > 0) {
            $discountCents = Money::percentOf($unitPriceCents, $percent);
        } else {
            $discountCents = (int) $voucher->discount_fixed_cents;
        }

        return max(0, min($discountCents, $unitPriceCents));
    }
}
