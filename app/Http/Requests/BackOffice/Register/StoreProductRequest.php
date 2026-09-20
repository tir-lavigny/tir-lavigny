<?php

namespace App\Http\Requests\BackOffice\Register;

use Illuminate\Foundation\Http\FormRequest;

class StoreProductRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('manage products');
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'picture' => ['nullable', 'image', 'max:2048'],
            'unit_price' => ['required', 'numeric', 'min:0'],
            'allows_custom_price' => ['boolean'],
            'is_stockable' => ['boolean'],
            'category_id' => ['required', 'exists:categories,id,deleted_at,NULL'],
        ];
    }
}
