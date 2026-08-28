<?php

namespace App\Rules\Register;

use App\Models\Voucher;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

class VoucherIsUsable implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        $request = request();
        $code = $request->input('code');
        $productId = $request->input('product_id');
        $seasonId = $request->input('season_id');

        if (! $code || ! $productId || ! $seasonId) {
            return;
        }

        $voucher = Voucher::where('code', $code)
            ->whereNull('deleted_at')
            ->first();

        if (! $voucher) {
            $fail('The selected voucher is invalid.');

            return;
        }

        if ($voucher->isUsed()) {
            $fail('The voucher has already been used.');

            return;
        }

        if ($voucher->product_id != $productId) {
            $fail('The voucher does not belong to this product.');

            return;
        }

        if ($voucher->season_id != $seasonId) {
            $fail('The voucher does not belong to this season.');

            return;
        }

        $season = $voucher->season;
        $now = now()->toDateString();

        if ($now < $season->start_date || $now > $season->end_date) {
            $fail('The voucher season is not currently active.');
        }
    }
}
