<?php

namespace App\Http\Requests\BackOffice\Stock;

use Illuminate\Foundation\Http\FormRequest;

class StoreStockLossRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('manage stock');
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
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
                'nullable',
                'string',
                'max:1000',
            ],
        ];
    }
}
