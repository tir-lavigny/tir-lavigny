<?php

namespace App\Http\Requests\API\Register;

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
            'items' => ['required', 'array', 'min:1'],
            'items.*.product_id' => ['required', 'exists:products,id,deleted_at,NULL'],
            'items.*.quantity' => ['required', 'integer', 'min:1'],
            'items.*.custom_unit_price' => ['nullable', 'numeric', 'min:0'],
            'items.*.voucher_code' => ['nullable', 'string', 'exists:vouchers,code,deleted_at,NULL'],
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            $items = $this->input('items', []);

            foreach ($items as $index => $item) {
                $productId = $item['product_id'] ?? null;
                $customPrice = $item['custom_unit_price'] ?? null;

                if (! $productId) {
                    continue;
                }

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
        });
    }
}
