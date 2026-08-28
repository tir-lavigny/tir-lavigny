<?php

namespace App\Http\Requests\API\Register;

use App\Rules\Register\VoucherIsUsable;
use Illuminate\Foundation\Http\FormRequest;

class VoucherValidateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('apply voucher to ticket');
    }

    public function rules(): array
    {
        return [
            'code' => ['required', 'string', 'exists:vouchers,code,deleted_at,NULL'],
            'product_id' => ['required', 'exists:products,id,deleted_at,NULL'],
            'season_id' => ['required', 'exists:seasons,id,deleted_at,NULL'],
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            if ($validator->errors()->any()) {
                return;
            }

            $this->validateVoucherUsability($validator);
        });
    }

    private function validateVoucherUsability($validator): void
    {
        $rule = new VoucherIsUsable;
        $rule->validate('code', $this->input('code'), function ($message) use ($validator) {
            $validator->errors()->add('code', $message);
        });
    }
}
