<?php

namespace App\Http\Requests\BackOffice;

use App\Enums\SwissCanton;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateMemberRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('manage members') ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'first_name' => ['sometimes', 'required', 'string', 'max:255'],
            'last_name' => ['sometimes', 'required', 'string', 'max:255'],
            'email' => ['sometimes', 'nullable', 'email', 'max:255'],
            'tel' => ['sometimes', 'nullable', 'string', 'max:255'],
            'avs' => ['sometimes', 'nullable', 'string', 'max:255'],
            'is_swiss_citizen' => ['sometimes', 'nullable', 'boolean'],
            'street' => ['sometimes', 'nullable', 'string', 'max:255'],
            'npa' => ['sometimes', 'nullable', 'string', 'max:255'],
            'city' => ['sometimes', 'nullable', 'string', 'max:255'],
            'canton' => ['sometimes', 'nullable', Rule::in(SwissCanton::validationRule())],
            'country' => ['sometimes', 'nullable', 'string', 'max:255'],
            'status' => ['sometimes', Rule::in(['active', 'passive'])],
        ];
    }
}
