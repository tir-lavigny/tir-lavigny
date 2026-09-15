<?php

namespace App\Http\Requests\BackOffice\Register;

use Illuminate\Foundation\Http\FormRequest;

class UpdateSeasonRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('manage seasons');
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'name' => ['sometimes', 'string', 'max:255'],
            'start_date' => ['sometimes', 'date'],
            'end_date' => ['sometimes', 'date', 'after_or_equal:start_date'],
            'initial_balance' => ['sometimes', 'numeric', 'min:0'],
        ];
    }
}
