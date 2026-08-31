<?php

namespace App\Http\Requests\BackOffice\Register;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateOperatingDayRequest extends FormRequest
{
    public function authorize(): bool
    {
        $status = $this->input('status');

        if ($status === 'open') {
            return $this->user()->can('reopen day');
        }

        if ($status === 'closed') {
            return $this->user()->can('close day');
        }

        return $this->user()->can('manage operating days');
    }

    public function rules(): array
    {
        return [
            'status' => ['sometimes', 'string', Rule::in(['open', 'closed'])],
            'cash_remark' => ['nullable', 'string', 'max:5000'],
        ];
    }
}
