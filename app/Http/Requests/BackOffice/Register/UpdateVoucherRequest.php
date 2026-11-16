<?php

namespace App\Http\Requests\BackOffice\Register;

use App\Models\Member;
use App\Models\Voucher;
use App\Rules\Register\OnlyOneDiscountType;
use Closure;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class UpdateVoucherRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('manage vouchers');
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'discount_percent' => ['nullable', 'numeric', 'between:0,100'],
            'discount_fixed' => ['nullable', 'numeric', 'min:0'],
            'product_id' => ['sometimes', 'exists:products,id,deleted_at,NULL'],
            'season_id' => ['sometimes', 'exists:seasons,id,deleted_at,NULL'],
            'member_id' => ['sometimes', $this->memberRule()],
        ];
    }

    private function memberRule(): Closure
    {
        return function (string $attribute, mixed $value, Closure $fail): void {
            $voucher = $this->route('voucher');

            if ($voucher instanceof Voucher && (int) $voucher->member_id === (int) $value) {
                if (! Member::withTrashed()->whereKey($value)->exists()) {
                    $fail('Le membre sélectionné est invalide.');
                }

                return;
            }

            if (! Member::whereKey($value)->exists()) {
                $fail('Le membre sélectionné est invalide.');
            }
        };
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function ($validator) {
            $rule = new OnlyOneDiscountType;
            $rule->validate('discount_percent', $this->input('discount_percent'), function (string $message) use ($validator) {
                return $validator->errors()->add('discount_percent', $message);
            });
        });
    }
}
