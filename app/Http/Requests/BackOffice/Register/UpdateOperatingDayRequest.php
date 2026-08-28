<?php

namespace App\Http\Requests\BackOffice\Register;

use Illuminate\Foundation\Http\FormRequest;

class UpdateOperatingDayRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('manage operating days');
    }

    public function rules(): array
    {
        return [
            'cash_remark' => ['nullable', 'string', 'max:5000'],
            'closing_balance' => ['nullable', 'numeric', 'min:0'],
        ];
    }
}
