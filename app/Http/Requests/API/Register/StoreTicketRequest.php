<?php

namespace App\Http\Requests\API\Register;

use App\Models\OperatingDay;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class StoreTicketRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('create ticket');
    }

    public function rules(): array
    {
        return [
            'payment_method' => ['required', 'string', Rule::in(['cash', 'twint'])],
            'operating_day_id' => ['required', 'exists:operating_days,id'],
            'member_id' => ['nullable', 'exists:members,id,deleted_at,NULL'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.product_id' => ['required', 'exists:products,id,deleted_at,NULL'],
            'items.*.quantity' => ['required', 'integer', 'min:1'],
            'items.*.custom_unit_price' => ['nullable', 'numeric', 'min:0'],
            'items.*.voucher_id' => ['nullable', 'integer', 'exists:vouchers,id,deleted_at,NULL'],
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            $items = $this->input('items', []);
            $usedVoucherIds = [];

            foreach ($items as $index => $item) {
                $productId = $item['product_id'] ?? null;
                $customPrice = $item['custom_unit_price'] ?? null;
                $voucherId = $item['voucher_id'] ?? null;

                if ($productId) {
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

                if ($voucherId) {
                    if (in_array($voucherId, $usedVoucherIds)) {
                        $validator->errors()->add(
                            "items.{$index}.voucher_id",
                            'Each voucher can only be used once per ticket.'
                        );
                    }
                    $usedVoucherIds[] = $voucherId;

                    $operatingDayId = $this->input('operating_day_id');
                    $day = OperatingDay::find($operatingDayId);

                    if ($day) {
                        $voucher = DB::table('vouchers')
                            ->where('id', $voucherId)
                            ->whereNull('deleted_at')
                            ->first();

                        if ($voucher) {
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
                                ->exists();

                            if ($isUsed) {
                                $validator->errors()->add(
                                    "items.{$index}.voucher_id",
                                    'The voucher has already been used.'
                                );
                            }

                            $memberId = $this->input('member_id');
                            if ($memberId && $voucher->member_id != $memberId) {
                                $validator->errors()->add(
                                    "items.{$index}.voucher_id",
                                    'The voucher does not belong to this member.'
                                );
                            }
                        }
                    }
                }
            }
        });
    }
}
