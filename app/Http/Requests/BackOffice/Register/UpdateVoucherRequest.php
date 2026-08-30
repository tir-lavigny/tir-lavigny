<?php

namespace App\Http\Requests\BackOffice\Register;

use App\Rules\Register\OnlyOneDiscountType;
use Illuminate\Foundation\Http\FormRequest;

class UpdateVoucherRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('manage vouchers');
    }

    public function rules(): array
    {
        return [
            'discount_percent' => ['nullable', 'numeric', 'between:0,100'],
            'discount_fixed' => ['nullable', 'numeric', 'min:0'],
            'product_id' => ['sometimes', 'exists:products,id,deleted_at,NULL'],
            'season_id' => ['sometimes', 'exists:seasons,id,deleted_at,NULL'],
            'member_id' => ['sometimes', 'exists:members,id,deleted_at,NULL'],
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            $rule = new OnlyOneDiscountType;
            $rule->validate('discount_percent', $this->input('discount_percent'), function ($message) use ($validator) {
                $validator->errors()->add('discount_percent', $message);
            });
        });
    }
}
