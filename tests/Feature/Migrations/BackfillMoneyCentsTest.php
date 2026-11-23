<?php

namespace Tests\Feature\Migrations;

use App\Models\Category;
use App\Models\Product;
use App\Models\Season;
use App\Models\Ticket;
use App\Models\Voucher;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class BackfillMoneyCentsTest extends TestCase
{
    use RefreshDatabase;

    private static ?Migration $migration = null;

    private function migration(): Migration
    {
        if (self::$migration === null) {
            $migration = require database_path('migrations/2026_11_23_000009_backfill_money_cents_columns.php');
            assert($migration instanceof Migration);
            self::$migration = $migration;
        }

        return self::$migration;
    }

    /**
     * Simulate a row written before the cents columns existed: the decimal
     * column holds the amount and the cents column is still null.
     */
    private function legacyProduct(string $unitPrice): Product
    {
        $product = Product::factory()->create([
            'category_id' => Category::factory()->create(['is_restricted' => false])->id,
        ]);

        DB::table('products')->where('id', $product->id)->update([
            'unit_price' => $unitPrice,
            'unit_price_cents' => null,
        ]);

        return $product->fresh();
    }

    public function test_it_converts_existing_decimal_amounts_to_cents(): void
    {
        $product = $this->legacyProduct('125.50');
        $ticket = Ticket::factory()->create();
        DB::table('tickets')->where('id', $ticket->id)->update(['total' => '18.90', 'total_cents' => null]);

        $voucher = Voucher::factory()->create(['discount_percent' => null, 'discount_fixed' => 5]);
        DB::table('vouchers')->where('id', $voucher->id)->update(['discount_fixed' => '7.25', 'discount_fixed_cents' => null]);

        $this->migration()->up();

        $this->assertSame(12550, (int) DB::table('products')->where('id', $product->id)->value('unit_price_cents'));
        $this->assertSame(1890, (int) DB::table('tickets')->where('id', $ticket->id)->value('total_cents'));
        $this->assertSame(725, (int) DB::table('vouchers')->where('id', $voucher->id)->value('discount_fixed_cents'));
    }

    public function test_it_leaves_null_amounts_null(): void
    {
        $season = Season::factory()->create();
        DB::table('seasons')->where('id', $season->id)->update(['end_balance' => null, 'end_balance_cents' => null]);

        $this->migration()->up();

        $this->assertNull(DB::table('seasons')->where('id', $season->id)->value('end_balance_cents'));
    }

    public function test_it_is_idempotent_and_never_overwrites_existing_cents(): void
    {
        $product = $this->legacyProduct('10.00');
        DB::table('products')->where('id', $product->id)->update(['unit_price_cents' => 4321]);

        $this->migration()->up();
        $this->migration()->up();

        $this->assertSame(4321, (int) DB::table('products')->where('id', $product->id)->value('unit_price_cents'));
    }

    public function test_down_clears_the_cents_columns_without_touching_the_decimal_columns(): void
    {
        $product = $this->legacyProduct('10.00');

        $this->migration()->up();
        $this->migration()->down();

        $row = DB::table('products')->where('id', $product->id)->first();

        $this->assertNull($row->unit_price_cents);
        $this->assertEquals(10.00, (float) $row->unit_price);
    }
}
