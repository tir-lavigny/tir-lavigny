<?php

namespace App\Http\Requests\BackOffice\Register;

use Illuminate\Foundation\Http\FormRequest;

class UpdateProductRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('manage products');
    }

    public function rules(): array
    {
        return [
            'name' => ['sometimes', 'string', 'max:255'],
            'picture' => ['nullable', 'image', 'max:2048'],
            'unit_price' => ['sometimes', 'numeric', 'min:0'],
            'allows_custom_price' => ['boolean'],
            'category_id' => ['sometimes', 'exists:categories,id,deleted_at,NULL'],
        ];
    }
}
