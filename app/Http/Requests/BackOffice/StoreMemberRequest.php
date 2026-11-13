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
            'tel' => ['nullable', 'string', 'max:20'],
            'avs' => ['required_if:is_swiss_citizen,true', 'nullable', 'string', 'max:20', 'regex:/^756\.\d{4}\.\d{4}\.\d{2}$/'],
            'is_swiss_citizen' => ['nullable', 'boolean'],
            'street' => ['nullable', 'string', 'max:255'],
            'npa' => ['nullable', 'string', 'max:10'],
            'city' => ['nullable', 'string', 'max:255'],
            'canton' => ['nullable', Rule::enum(SwissCanton::class)],
            'country' => ['nullable', 'string', 'max:100'],
            'status' => ['required', Rule::in(['active', 'passive'])],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'avs.regex' => 'Le numéro AVS doit être au format 756.XXXX.XXXX.XX.',
            'avs.required_if' => 'Le numéro AVS est requis pour les citoyens suisses.',
        ];
    }
}
