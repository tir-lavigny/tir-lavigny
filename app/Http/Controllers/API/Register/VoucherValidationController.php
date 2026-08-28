<?php

namespace App\Http\Controllers\API\Register;

use App\Http\Controllers\Controller;
use App\Http\Requests\API\Register\VoucherValidateRequest;
use App\Models\Voucher;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;

class VoucherValidationController extends Controller
{
    public function validate(VoucherValidateRequest $request): JsonResponse
    {
        $voucher = Voucher::where('code', $request->input('code'))->first();

        if ($voucher->isUsed()) {
            return response()->json([
                'data' => [
                    'valid' => false,
                    'error' => 'This voucher has already been used.',
                ],
            ]);
        }

        if ($voucher->product_id !== (int) $request->input('product_id')) {
            return response()->json([
                'data' => [
                    'valid' => false,
                    'error' => 'This voucher is not valid for the selected product.',
                ],
            ]);
        }

        if ($voucher->season_id !== (int) $request->input('season_id')) {
            return response()->json([
                'data' => [
                    'valid' => false,
                    'error' => 'This voucher is not valid for the selected season.',
                ],
            ]);
        }

        $season = $voucher->season;
        $today = Carbon::today();

        if ($today->lt($season->start_date) || $today->gt($season->end_date)) {
            return response()->json([
                'data' => [
                    'valid' => false,
                    'error' => 'This voucher\'s season is not currently active.',
                ],
            ]);
        }

        $type = $voucher->discount_percent !== null ? 'percent' : 'fixed';
        $value = $type === 'percent' ? $voucher->discount_percent : $voucher->discount_fixed;

        return response()->json([
            'data' => [
                'valid' => true,
                'type' => $type,
                'value' => (float) $value,
            ],
        ]);
    }
}
