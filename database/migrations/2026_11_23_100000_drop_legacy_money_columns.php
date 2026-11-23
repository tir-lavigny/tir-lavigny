<?php

use App\Support\Money;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Phase 2 of the integer-cents refactor: money lives only in the *_cents
 * columns, so the legacy decimal columns are dropped.
 *
 * One file, as requested, but every drop is guarded and issued on its own so a
 * partially applied run can simply be re-run. The down() restores the columns
 * and copies the amounts back from cents (exact at two decimals), which makes
 * the change reversible.
 */
return new class extends Migration
{
    /** @var array<string, list<string>> */
    private const COLUMNS = [
        'products' => ['unit_price'],
        'price_list_items' => ['unit_price'],
        'vouchers' => ['discount_fixed'],
        'seasons' => ['initial_balance', 'end_balance'],
        'operating_days' => ['closing_balance', 'total_cash', 'total_twint'],
        'cash_movements' => ['amount'],
        'tickets' => ['total'],
        'ticket_items' => ['unit_price', 'subtotal'],
    ];

    public function up(): void
    {
        foreach (self::COLUMNS as $table => $columns) {
            foreach ($columns as $column) {
                if (Schema::hasTable($table) && Schema::hasColumn($table, $column)) {
                    Schema::table($table, function (Blueprint $blueprint) use ($column) {
                        $blueprint->dropColumn($column);
                    });
                }
            }
        }
    }

    public function down(): void
    {
        foreach (self::COLUMNS as $table => $columns) {
            foreach ($columns as $column) {
                if (! Schema::hasTable($table)) {
                    continue;
                }

                if (! Schema::hasColumn($table, $column)) {
                    Schema::table($table, function (Blueprint $blueprint) use ($column) {
                        $blueprint->decimal($column, 10, 2)->nullable();
                    });
                }

                if (Schema::hasColumn($table, $column.'_cents')) {
                    $this->restoreFromCents($table, $column);
                }
            }
        }
    }

    private function restoreFromCents(string $table, string $column): void
    {
        DB::table($table)
            ->select(['id', $column.'_cents'])
            ->chunkById(500, function ($rows) use ($table, $column): void {
                foreach ($rows as $row) {
                    $cents = $row->{$column.'_cents'};

                    if ($cents === null) {
                        continue;
                    }

                    DB::table($table)
                        ->where('id', $row->id)
                        ->update([$column => Money::fromCents((int) $cents)]);
                }
            });
    }
};
