<?php

namespace App\Rules\Register;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

class OnlyOneDiscountType implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        $request = request();
        $hasPercent = $request->filled('discount_percent');
        $hasFixed = $request->filled('discount_fixed');

        if ($hasPercent && $hasFixed) {
            $fail('Only one of discount_percent or discount_fixed may be provided.');
        }
    }
}
