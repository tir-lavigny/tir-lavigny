<?php

namespace App\Http\Requests\BackOffice\Stock;

use Illuminate\Foundation\Http\FormRequest;

class CompleteStockCountRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('manage stock');
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'lines' => [
                'required',
                'array',
                'min:1',
            ],
            'lines.*.product_id' => [
                'required',
                'integer',
                'distinct',
                'exists:products,id',
            ],
            'lines.*.counted_quantity' => [
                'required',
                'integer',
            ],
        ];
    }
}
