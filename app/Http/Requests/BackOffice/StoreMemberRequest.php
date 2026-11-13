<?php

namespace App\Http\Requests\BackOffice;

use App\Enums\SwissCanton;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreMemberRequest extends FormRequest
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
            'first_name' => ['required', 'string', 'max:255'],
            'last_name' => ['required', 'string', 'max:255'],
            'email' => ['nullable', 'email', 'max:255'],
            'tel' => ['nullable', 'string', 'max:255'],
            'avs' => ['nullable', 'string', 'max:255'],
            'is_swiss_citizen' => ['nullable', 'boolean'],
            'street' => ['nullable', 'string', 'max:255'],
            'npa' => ['nullable', 'string', 'max:255'],
            'city' => ['nullable', 'string', 'max:255'],
            'canton' => ['nullable', Rule::enum(SwissCanton::class)],
            'country' => ['nullable', 'string', 'max:255'],
            'status' => ['required', Rule::in(['active', 'passive'])],
        ];
    }
}
