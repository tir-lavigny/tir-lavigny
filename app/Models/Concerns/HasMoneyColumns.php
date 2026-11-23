<?php

namespace App\Models\Concerns;

use App\Support\Money;
use Illuminate\Database\Eloquent\Casts\Attribute;

/**
 * Exposes an integer cents column under its historical decimal attribute name.
 *
 * Money is stored in cents only. The decimal attribute name stays the public
 * contract — forms post `unit_price`, JSON returns `"12.50"` — and is converted
 * at the model boundary. Models hide their cents columns and append the decimal
 * names, so serialized payloads keep their historical shape.
 *
 * This helper deliberately has no native Attribute return type: Laravel's
 * getAttributeMarkedMutatorMethods() invokes every method whose declared return
 * type is Attribute with no arguments to build the mutator cache, which would
 * explode on the required $column parameter.
 */
trait HasMoneyColumns
{
    /**
     * @return Attribute<string|null, array<string, int|null>>
     */
    protected function moneyAttribute(string $column)
    {
        return Attribute::make(
            get: function (mixed $value, array $attributes) use ($column): ?string {
                $cents = $attributes[$column.'_cents'] ?? null;

                return $cents === null ? null : Money::fromCents((int) $cents);
            },
            set: function (mixed $value) use ($column): array {
                return [$column.'_cents' => Money::toCents($value)];
            },
        );
    }
}
