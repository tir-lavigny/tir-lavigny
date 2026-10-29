<?php

namespace App\Services;

use App\Models\OperatingDay;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Validator;

class TicketValidationService
{
    /**
     * Validate ticket items against business rules.
     */
    /** @param array<int, array<string, mixed>>  $items */
    public function validateItems(array $items, int $operatingDayId, ?int $memberId, Validator $validator): void
    {
        $usedVoucherIds = [];

        foreach ($items as $index => $item) {
            $productId = $item['product_id'] ?? null;
            $customPrice = $item['custom_unit_price'] ?? null;
            $quantity = $item['quantity'] ?? null;
            $rawVoucherIds = $item['voucher_ids'] ?? [];
            $voucherIds = is_array($rawVoucherIds) ? $rawVoucherIds : [];

            if ($productId) {
                $this->validateCustomPrice($productId, $customPrice, $index, $validator);
            }

            if (! $voucherIds) {
                continue;
            }

            $errorKey = "items.{$index}.voucher_ids";

            $this->validateVoucherCount($voucherIds, $quantity, $errorKey, $validator);

            $seenInItem = [];

            foreach ($voucherIds as $voucherIndex => $voucherId) {
                if (empty($voucherId)) {
                    continue;
                }

                if (in_array($voucherId, $seenInItem, true)) {
                    $validator->errors()->add($errorKey, 'Each voucher can only be used once per ticket.');

                    continue;
                }
                $seenInItem[] = $voucherId;

                if (in_array($voucherId, $usedVoucherIds, true)) {
                    $validator->errors()->add($errorKey, 'Each voucher can only be used once per ticket.');

                    continue;
                }
                $usedVoucherIds[] = $voucherId;

                $this->validateVoucherRules($voucherId, $productId, $operatingDayId, $memberId, "items.{$index}.voucher_ids.{$voucherIndex}", $validator);
            }
        }
    }

    /** @param int|string|null $customPrice */
    private function validateCustomPrice(int $productId, $customPrice, int $index, Validator $validator): void
    {
        $allowsCustom = DB::table('products')
            ->where('id', $productId)
            ->whereNull('deleted_at')
            ->value('allows_custom_price');

        if ($allowsCustom && ($customPrice === null || $customPrice === '')) {
            $validator->errors()->add(
                "items.{$index}.custom_unit_price",
                'The custom unit price is required for this product.'
            );
        }
    }

    /** @param array<int, int>  $voucherIds */
    private function validateVoucherCount(array $voucherIds, $quantity, string $errorKey, Validator $validator): void
    {
        if ($quantity === null || count($voucherIds) <= $quantity) {
            return;
        }

        $validator->errors()->add(
            $errorKey,
            'You cannot apply more vouchers than the quantity of the product.'
        );
    }

    private function validateVoucherRules(int $voucherId, ?int $productId, int $operatingDayId, ?int $memberId, string $errorKey, Validator $validator): void
    {
        $day = OperatingDay::find($operatingDayId);

        if (! $day) {
            return;
        }

        $voucher = DB::table('vouchers')
            ->where('id', $voucherId)
            ->whereNull('deleted_at')
            ->first();

        if (! $voucher) {
            return;
        }

        if ($voucher->product_id != $productId) {
            $validator->errors()->add(
                $errorKey,
                'The voucher does not match the selected product.'
            );
        }

        if ($voucher->season_id != $day->season_id) {
            $validator->errors()->add(
                $errorKey,
                'The voucher is not valid for the current season.'
            );
        }

        $isUsed = DB::table('ticket_items')
            ->where('voucher_id', $voucherId)
            ->whereNull('deleted_at')
            ->exists();

        if ($isUsed) {
            $validator->errors()->add(
                $errorKey,
                'The voucher has already been used.'
            );
        }

        if ($memberId && $voucher->member_id != $memberId) {
            $validator->errors()->add(
                $errorKey,
                'The voucher does not belong to this member.'
            );
        }
    }
}
