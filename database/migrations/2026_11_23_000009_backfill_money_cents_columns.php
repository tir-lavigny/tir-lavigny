<?php

use App\Support\Money;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Copy every existing decimal money amount into its integer cents column.
 *
 * Data-only migration, separate from the schema additions (see
 * database/migrations/POLICY.md): the legacy columns stay untouched, so the
 * conversion can be re-run safely (rows that already have cents are skipped)
 * and rolled back without losing anything.
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
            $this->backfill($table, $columns);
        }
    }

    public function down(): void
    {
        foreach (self::COLUMNS as $table => $columns) {
            foreach ($columns as $column) {
                if (Schema::hasColumn($table, $column.'_cents')) {
                    DB::table($table)->update([$column.'_cents' => null]);
                }
            }
        }
    }

    /**
     * @param  list<string>  $columns
     */
    private function backfill(string $table, array $columns): void
    {
        if (! Schema::hasTable($table)) {
            return;
        }

        $columns = array_values(array_filter(
            $columns,
            fn (string $column): bool => Schema::hasColumn($table, $column)
                && Schema::hasColumn($table, $column.'_cents')
        ));

        if ($columns === []) {
            return;
        }

        $select = array_merge(['id'], $columns, array_map(fn (string $column): string => $column.'_cents', $columns));

        DB::table($table)
            ->select($select)
            ->chunkById(500, function ($rows) use ($table, $columns): void {
                foreach ($rows as $row) {
                    $update = [];

                    foreach ($columns as $column) {
                        $cents = $row->{$column.'_cents'};

                        if ($cents !== null) {
                            continue;
                        }

                        $value = Money::toCents($row->{$column});

                        if ($value !== null) {
                            $update[$column.'_cents'] = $value;
                        }
                    }

                    if ($update !== []) {
                        DB::table($table)->where('id', $row->id)->update($update);
                    }
                }
            });
    }
};
