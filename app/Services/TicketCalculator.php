<?php

namespace App\Services;

use App\Models\Product;
use App\Models\Voucher;

class TicketCalculator
{
    /**
     * Compute ticket total and line items from request data.
     *
     * @param  array<int, array{product_id: int, quantity: int, custom_unit_price?: float, voucher_id?: int}>  $items
     * @return array{total: float, items: array<int, array{product_id: int, voucher_id: int|null, quantity: int, unit_price: float, subtotal: float}>}
     */
    public function compute(array $items): array
    {
        $computedItems = [];
        $total = 0;

        foreach ($items as $item) {
            $product = Product::findOrFail($item['product_id']);
            $unitPrice = $item['custom_unit_price'] ?? $product->unit_price;
            $quantity = $item['quantity'];
            $discount = 0;
            $voucherId = null;

            if (! empty($item['voucher_id'])) {
                $voucher = Voucher::findOrFail($item['voucher_id']);
                $voucherId = $voucher->id;

                if ($voucher->discount_percent !== null) {
                    $discount = $unitPrice * ($voucher->discount_percent / 100);
                } else {
                    $discount = $voucher->discount_fixed;
                }
            }

            $subtotal = ($unitPrice * $quantity) - $discount;
            $total += $subtotal;

            $computedItems[] = [
                'product_id' => $product->id,
                'voucher_id' => $voucherId,
                'quantity' => $quantity,
                'unit_price' => $unitPrice,
                'subtotal' => $subtotal,
            ];
        }

        return [
            'total' => $total,
            'items' => $computedItems,
        ];
    }
}
