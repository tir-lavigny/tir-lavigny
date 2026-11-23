<?php

use App\Support\Money;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Last safety pass before the legacy decimal columns are dropped.
 *
 * The phase-1 backfill and the model dual-write should already have filled
 * every *_cents column, but amounts written around the migration (raw SQL,
 * manual fixes, imports) may have bypassed both. Copy any remaining decimal
 * amount one final time so dropping the column can never lose money.
 *
 * Data-only, guarded and idempotent: rows that already have cents are skipped
 * and the migration can be re-run safely.
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
            if (! Schema::hasTable($table)) {
                continue;
            }

            foreach ($columns as $column) {
                if (Schema::hasColumn($table, $column) && Schema::hasColumn($table, $column.'_cents')) {
                    $this->backfill($table, $column);
                }
            }
        }
    }

    public function down(): void
    {
        // The decimal columns stay the source of truth, nothing to undo.
    }

    private function backfill(string $table, string $column): void
    {
        DB::table($table)
            ->select(['id', $column])
            ->whereNotNull($column)
            ->whereNull($column.'_cents')
            ->chunkById(500, function ($rows) use ($table, $column): void {
                foreach ($rows as $row) {
                    $cents = Money::toCents($row->{$column});

                    if ($cents === null) {
                        continue;
                    }

                    DB::table($table)
                        ->where('id', $row->id)
                        ->update([$column.'_cents' => $cents]);
                }
            });
    }
};
