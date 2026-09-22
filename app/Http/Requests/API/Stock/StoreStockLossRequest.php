<?php

namespace App\Http\Requests\API\Stock;

use Illuminate\Foundation\Http\FormRequest;

class StoreStockLossRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('record stock loss');
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'operating_day_id' => [
                'required',
                'integer',
                'exists:operating_days,id',
            ],
            'stock_location_id' => [
                'required',
                'integer',
                'exists:stock_locations,id',
            ],
            'product_id' => [
                'required',
                'integer',
                'exists:products,id',
            ],
            'quantity' => [
                'required',
                'integer',
                'min:1',
            ],
            'reason' => [
                'required',
                'string',
                'max:1000',
            ],
        ];
    }
}
