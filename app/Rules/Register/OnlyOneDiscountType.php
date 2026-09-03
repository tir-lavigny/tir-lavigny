<?php

namespace App\Rules\Register;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

class OnlyOneDiscountType implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        $request = request();
        $hasPercent = (float) $request->input('discount_percent', 0) > 0;
        $hasFixed = (float) $request->input('discount_fixed', 0) > 0;

        if ($hasPercent && $hasFixed) {
            $fail('Only one of discount_percent or discount_fixed may be provided.');
        }
    }
}
