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
    /** @param array<int, array<string, mixed>> $items */
    public function validateItems(array $items, int $operatingDayId, ?int $memberId, Validator $validator): void
    {
        $usedVoucherIds = [];

        foreach ($items as $index => $item) {
            $productId = $item['product_id'] ?? null;
            $customPrice = $item['custom_unit_price'] ?? null;
            $voucherId = $item['voucher_id'] ?? null;

            if ($productId) {
                $this->validateCustomPrice($productId, $customPrice, $index, $validator);
            }

            if ($voucherId) {
                $this->validateVoucherUniqueness($voucherId, $usedVoucherIds, $index, $validator);
                $usedVoucherIds[] = $voucherId;

                $this->validateVoucherRules($voucherId, $productId, $operatingDayId, $memberId, $index, $validator);
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

    /** @param array<int, int> $usedVoucherIds */
    private function validateVoucherUniqueness(int $voucherId, array $usedVoucherIds, int $index, Validator $validator): void
    {
        if (in_array($voucherId, $usedVoucherIds)) {
            $validator->errors()->add(
                "items.{$index}.voucher_id",
                'Each voucher can only be used once per ticket.'
            );
        }
    }

    private function validateVoucherRules(int $voucherId, ?int $productId, int $operatingDayId, ?int $memberId, int $index, Validator $validator): void
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
                "items.{$index}.voucher_id",
                'The voucher does not match the selected product.'
            );
        }

        if ($voucher->season_id != $day->season_id) {
            $validator->errors()->add(
                "items.{$index}.voucher_id",
                'The voucher is not valid for the current season.'
            );
        }

        $isUsed = DB::table('ticket_items')
            ->where('voucher_id', $voucherId)
            ->whereNull('deleted_at')
            ->exists();

        if ($isUsed) {
            $validator->errors()->add(
                "items.{$index}.voucher_id",
                'The voucher has already been used.'
            );
        }

        if ($memberId && $voucher->member_id != $memberId) {
            $validator->errors()->add(
                "items.{$index}.voucher_id",
                'The voucher does not belong to this member.'
            );
        }
    }
}
