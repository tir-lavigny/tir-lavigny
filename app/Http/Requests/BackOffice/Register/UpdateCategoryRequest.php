<?php

namespace App\Http\Requests\BackOffice\Register;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateCategoryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('manage categories');
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'name' => ['sometimes', 'string', 'max:255', Rule::unique('categories')->ignore($this->route('category'))],
            'is_restricted' => ['boolean'],
        ];
    }
}
