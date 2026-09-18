<?php

namespace App\Http\Requests\BackOffice\Stock;

use App\Enums\StockCountType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreStockCountRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('manage stock');
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'stock_location_id' => [
                'required',
                'integer',
                'exists:stock_locations,id',
            ],
            'type' => [
                'required',
                Rule::enum(StockCountType::class),
            ],
        ];
    }
}
