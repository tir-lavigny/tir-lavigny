<?php

namespace App\Http\Requests\BackOffice\Register;

use App\Rules\Register\OnlyOneDiscountType;
use Illuminate\Foundation\Http\FormRequest;

class StoreVoucherRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('manage vouchers');
    }

    public function rules(): array
    {
        return [
            'discount_percent' => ['required_without:discount_fixed', 'nullable', 'numeric', 'between:0,100'],
            'discount_fixed' => ['required_without:discount_percent', 'nullable', 'numeric', 'min:0'],
            'product_id' => ['required', 'exists:products,id,deleted_at,NULL'],
            'season_id' => ['required', 'exists:seasons,id,deleted_at,NULL'],
            'member_id' => ['required', 'exists:members,id,deleted_at,NULL'],
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            $rule = new OnlyOneDiscountType;
            $rule->validate('discount_percent', $this->input('discount_percent'), function (string $message) use ($validator): void {
                $validator->errors()->add('discount_percent', $message);
            });
        });
    }
}
