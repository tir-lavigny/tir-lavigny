<?php

namespace App\Http\Requests\BackOffice\Stock;

use Illuminate\Foundation\Http\FormRequest;

class StoreStockTransferRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('manage stock');
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'to_location_id' => [
                'required',
                'integer',
                'exists:stock_locations,id',
            ],
            'items' => [
                'required',
                'array',
                'min:1',
            ],
            'items.*.product_id' => [
                'required',
                'integer',
                'distinct',
                'exists:products,id',
            ],
            'items.*.quantity' => [
                'required',
                'integer',
                'min:1',
            ],
            'reason' => [
                'nullable',
                'string',
                'max:1000',
            ],
        ];
    }
}
