<?php

namespace App\Models\Concerns;

use App\Support\Money;
use Illuminate\Database\Eloquent\Casts\Attribute;

/**
 * Two-way binding between a legacy decimal money column and its integer cents
 * sibling.
 *
 * Phase 1 of the money refactor keeps both columns: the cents column is the
 * source of truth for logic and aggregates, while the decimal column is
 * mirrored so an application rollback still reads valid data and existing
 * serialized payloads keep the exact same shape.
 */
trait HasMoneyColumns
{
    /**
     * Attribute exposed under the legacy decimal column name.
     *
     * Reads from the cents column when present, falls back to the decimal
     * column for rows written before the cents columns existed. Writes both.
     *
     * @return Attribute<string|null, array<string, int|string|null>>
     */
    protected function legacyMoneyAttribute(string $column)
    {
        return Attribute::make(
            get: function (mixed $value, array $attributes) use ($column): ?string {
                $cents = $attributes[$column.'_cents'] ?? null;

                if ($cents !== null) {
                    return Money::fromCents((int) $cents);
                }

                return $value === null ? null : Money::fromCents(Money::toCents($value));
            },
            set: function (mixed $value) use ($column): array {
                $cents = Money::toCents($value);

                return [
                    $column.'_cents' => $cents,
                    $column => Money::fromCents($cents),
                ];
            },
        );
    }

    /**
     * Integer cents attribute, mirroring the legacy decimal column on write.
     */
    /** @return Attribute<int|null, array<string, int|string|null>> */
    protected function centsMoneyAttribute(string $column)
    {
        return Attribute::make(
            get: function (mixed $value, array $attributes) use ($column): ?int {
                if ($value !== null) {
                    return (int) $value;
                }

                $legacy = $attributes[$column] ?? null;

                return $legacy === null ? null : Money::toCents($legacy);
            },
            set: function (mixed $value) use ($column): array {
                $cents = $value === null ? null : (int) $value;

                return [
                    $column.'_cents' => $cents,
                    $column => Money::fromCents($cents),
                ];
            },
        );
    }
}
