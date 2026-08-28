<?php

namespace App\Rules\Register;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Support\Facades\DB;

class RequiredIfProductAllowsCustomPrice implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        $request = request();
        $parts = explode('.', $attribute);
        $itemIndex = $parts[1];
        $productId = $request->input("items.{$itemIndex}.product_id");

        if (! $productId) {
            return;
        }

        $allowsCustom = DB::table('products')
            ->where('id', $productId)
            ->whereNull('deleted_at')
            ->value('allows_custom_price');

        if ($allowsCustom && ($value === null || $value === '')) {
            $fail('The custom unit price is required for this product.');
        }
    }
}
