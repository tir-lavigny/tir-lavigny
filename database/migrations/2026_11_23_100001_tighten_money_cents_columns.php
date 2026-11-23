<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * The legacy decimal columns were NOT NULL (with a zero default for the
 * operating day totals); the cents columns inherited none of that, so a missed
 * write silently produced a null amount. Tighten the columns that must always
 * hold a value.
 *
 * Nullable on purpose: operating_days.closing_balance_cents (open day),
 * vouchers.discount_fixed_cents (no fixed discount) and
 * seasons.end_balance_cents (derived, unused).
 *
 * SQLite cannot change nullability without rebuilding the table, and the
 * migration transaction ignores PRAGMA foreign_keys, so the rebuild would
 * cascade-delete children. The data checks still run there; the constraint is
 * applied on MySQL/MariaDB only.
 */
return new class extends Migration
{
    /** @var array<string, list<string>> */
    private const REQUIRED = [
        'products' => ['unit_price_cents'],
        'price_list_items' => ['unit_price_cents'],
        'seasons' => ['initial_balance_cents'],
        'cash_movements' => ['amount_cents'],
        'tickets' => ['total_cents'],
        'ticket_items' => ['unit_price_cents', 'subtotal_cents'],
    ];

    /** @var array<string, list<string>> */
    private const REQUIRED_WITH_DEFAULT = [
        'operating_days' => ['total_cash_cents', 'total_twint_cents'],
    ];

    public function up(): void
    {
        foreach (self::REQUIRED_WITH_DEFAULT as $table => $columns) {
            foreach ($columns as $column) {
                if (! Schema::hasColumn($table, $column)) {
                    continue;
                }

                DB::table($table)->whereNull($column)->update([$column => 0]);
                $this->tighten($table, $column, 0);
            }
        }

        foreach (self::REQUIRED as $table => $columns) {
            foreach ($columns as $column) {
                if (! Schema::hasColumn($table, $column)) {
                    continue;
                }

                $this->assertFilled($table, $column);
                $this->tighten($table, $column, null);
            }
        }
    }

    public function down(): void
    {
        foreach (self::REQUIRED + self::REQUIRED_WITH_DEFAULT as $table => $columns) {
            foreach ($columns as $column) {
                if (! Schema::hasColumn($table, $column)) {
                    continue;
                }

                $this->loosen($table, $column);
            }
        }
    }

    private function assertFilled(string $table, string $column): void
    {
        $missing = DB::table($table)->whereNull($column)->count();

        if ($missing > 0) {
            throw new RuntimeException(
                "Cannot make {$table}.{$column} NOT NULL: {$missing} row(s) have no amount. "
                .'Re-run the money cents backfill before migrating.'
            );
        }
    }

    private function tighten(string $table, string $column, ?int $default): void
    {
        if (! $this->canAlterColumns()) {
            return;
        }

        Schema::table($table, function (Blueprint $blueprint) use ($column, $default) {
            $definition = $blueprint->bigInteger($column);

            if ($default !== null) {
                $definition->default($default);
            }

            $definition->change();
        });
    }

    private function loosen(string $table, string $column): void
    {
        if (! $this->canAlterColumns()) {
            return;
        }

        Schema::table($table, function (Blueprint $blueprint) use ($column) {
            $blueprint->bigInteger($column)->nullable()->default(null)->change();
        });
    }

    private function canAlterColumns(): bool
    {
        return Schema::getConnection()->getDriverName() !== 'sqlite';
    }
};
