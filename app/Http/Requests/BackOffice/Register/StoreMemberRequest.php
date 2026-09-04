<?php

namespace App\Http\Requests\BackOffice\Register;

use Illuminate\Foundation\Http\FormRequest;

class StoreMemberRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('manage members');
    }

    public function rules(): array
    {
        return [
            'first_name' => ['required', 'string', 'max:255'],
            'last_name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255'],
            'is_swiss' => ['required', 'boolean'],
            'tel' => ['nullable', 'string', 'max:255'],
        ];
    }
}
