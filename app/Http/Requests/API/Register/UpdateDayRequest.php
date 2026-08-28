<?php

namespace App\Http\Requests\API\Register;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateDayRequest extends FormRequest
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

        return $this->user()->can('add cash remark');
    }

    public function rules(): array
    {
        return [
            'status' => ['sometimes', 'string', Rule::in(['open', 'closed'])],
            'closing_balance' => ['nullable', 'numeric', 'min:0'],
            'cash_remark' => ['nullable', 'string', 'max:5000'],
        ];
    }
}
