<?php

namespace App\Http\Requests\BackOffice;

use App\Rules\UniqueMemberAvs;
use Illuminate\Foundation\Http\FormRequest;

class UpdateMemberRegistrationAvsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('manage member registration')
            && $this->user()->can('view member sensitive data');
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'avs' => [
                'required',
                'string',
                'max:20',
                'regex:/^756\.\d{4}\.\d{4}\.\d{2}$/',
                new UniqueMemberAvs,
            ],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'avs.regex' => 'Le numéro AVS doit être au format 756.XXXX.XXXX.XX.',
        ];
    }
}
