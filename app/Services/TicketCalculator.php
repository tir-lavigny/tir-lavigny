<?php

namespace App\Services;

use App\Models\Product;
use App\Models\Voucher;

class TicketCalculator
{
    /**
     * Compute ticket total and line items from request data.
     *
     * Each voucher discounts exactly one unit of its product. A request line
     * with vouchers expands into one computed row per voucher (quantity 1)
     * plus one remainder row without voucher.
     *
     * @param  array<int, array{product_id: int, quantity: int, custom_unit_price?: float, voucher_ids?: array<int, int|null>}>  $items
     * @return array{total: float, items: array<int, array{product_id: int, voucher_id: int|null, quantity: int, unit_price: float, subtotal: float}>}
     */
    public function compute(array $items): array
    {
        $computedItems = [];
        $total = 0;

        foreach ($items as $item) {
            $product = Product::findOrFail($item['product_id']);
            $unitPrice = (float) ($item['custom_unit_price'] ?? $product->unit_price);
            $quantity = (int) $item['quantity'];

            $rawVoucherIds = $item['voucher_ids'] ?? [];
            $voucherIds = array_values(array_filter(
                is_array($rawVoucherIds) ? $rawVoucherIds : [],
                fn ($voucherId) => ! empty($voucherId)
            ));

            $vouchers = [];
            foreach ($voucherIds as $voucherId) {
                $vouchers[] = Voucher::findOrFail($voucherId);
            }

            foreach ($vouchers as $voucher) {
                $discount = $this->unitDiscount($voucher, $unitPrice);
                $voucherId = (int) $voucher->id;
                $subtotal = round($unitPrice - $discount, 2);
                $total += $subtotal;

                $computedItems[] = [
                    'product_id' => $product->id,
                    'voucher_id' => $voucherId,
                    'quantity' => 1,
                    'unit_price' => $unitPrice,
                    'subtotal' => $subtotal,
                ];
            }

            $remaining = $quantity - count($vouchers);

            if ($remaining > 0) {
                $subtotal = round($unitPrice * $remaining, 2);
                $total += $subtotal;

                $computedItems[] = [
                    'product_id' => $product->id,
                    'voucher_id' => null,
                    'quantity' => $remaining,
                    'unit_price' => $unitPrice,
                    'subtotal' => $subtotal,
                ];
            }
        }

        return [
            'total' => round($total, 2),
            'items' => $computedItems,
        ];
    }

    /**
     * Discount one voucher applies to one unit, never more than the unit price.
     */
    private function unitDiscount(Voucher $voucher, float $unitPrice): float
    {
        $discount = $voucher->discount_percent !== null
            ? $unitPrice * ((float) $voucher->discount_percent / 100)
            : (float) $voucher->discount_fixed;

        return round(min(max($discount, 0), $unitPrice), 2);
    }
}
