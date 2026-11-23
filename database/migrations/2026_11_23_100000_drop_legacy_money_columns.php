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
 * partially applied run can simply be re-run. A column is only dropped when its
 * cents sibling exists, so a drifted database never loses the only copy of an
 * amount.
 *
 * The down() restores the columns with their original nullability and defaults
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

    /**
     * Original column definitions, so down() restores the schema phase 1 ran
     * against.
     *
     * @var array<string, array<string, array{nullable: bool, default: int|null}>>
     */
    private const DEFINITIONS = [
        'products' => [
            'unit_price' => ['nullable' => false, 'default' => null],
        ],
        'price_list_items' => [
            'unit_price' => ['nullable' => false, 'default' => null],
        ],
        'vouchers' => [
            'discount_fixed' => ['nullable' => true, 'default' => null],
        ],
        'seasons' => [
            'initial_balance' => ['nullable' => false, 'default' => null],
            'end_balance' => ['nullable' => true, 'default' => null],
        ],
        'operating_days' => [
            'closing_balance' => ['nullable' => true, 'default' => null],
            'total_cash' => ['nullable' => false, 'default' => 0],
            'total_twint' => ['nullable' => false, 'default' => 0],
        ],
        'cash_movements' => [
            'amount' => ['nullable' => false, 'default' => null],
        ],
        'tickets' => [
            'total' => ['nullable' => false, 'default' => null],
        ],
        'ticket_items' => [
            'unit_price' => ['nullable' => false, 'default' => null],
            'subtotal' => ['nullable' => false, 'default' => null],
        ],
    ];

    public function up(): void
    {
        foreach (self::COLUMNS as $table => $columns) {
            foreach ($columns as $column) {
                if (! $this->canDrop($table, $column)) {
                    continue;
                }

                Schema::table($table, function (Blueprint $blueprint) use ($column) {
                    $blueprint->dropColumn($column);
                });
            }
        }
    }

    public function down(): void
    {
        foreach (self::DEFINITIONS as $table => $columns) {
            if (! Schema::hasTable($table)) {
                continue;
            }

            foreach ($columns as $column => $definition) {
                if (! Schema::hasColumn($table, $column)) {
                    $this->addColumn($table, $column, $definition);
                }

                if (Schema::hasColumn($table, $column.'_cents')) {
                    $this->restoreFromCents($table, $column);
                }
            }
        }
    }

    private function canDrop(string $table, string $column): bool
    {
        return Schema::hasTable($table)
            && Schema::hasColumn($table, $column)
            && Schema::hasColumn($table, $column.'_cents');
    }

    /**
     * Add the column with its original nullability and default in one pass.
     *
     * SQLite refuses to add a NOT NULL column without a default to a populated
     * table, and tightening it afterwards would rebuild the table: inside the
     * migration transaction its PRAGMA foreign_keys toggle is ignored, so the
     * rebuild would cascade-delete children. On SQLite those columns come back
     * nullable; MySQL/MariaDB alter in place, so the production schema matches.
     *
     * @param  array{nullable: bool, default: int|null}  $definition
     */
    private function addColumn(string $table, string $column, array $definition): void
    {
        $required = ! $definition['nullable']
            && ($definition['default'] !== null || $this->supportsRequiredColumns());

        Schema::table($table, function (Blueprint $blueprint) use ($column, $definition, $required) {
            $columnDefinition = $blueprint->decimal($column, 10, 2);

            if (! $required) {
                $columnDefinition->nullable();
            }

            if ($definition['default'] !== null) {
                $columnDefinition->default($definition['default']);
            }
        });
    }

    private function supportsRequiredColumns(): bool
    {
        return Schema::getConnection()->getDriverName() !== 'sqlite';
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
