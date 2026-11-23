<?php

namespace Tests\Feature\Migrations;

use App\Models\OperatingDay;
use App\Models\Product;
use App\Models\Season;
use App\Models\Ticket;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class DropLegacyMoneyColumnsTest extends TestCase
{
    use RefreshDatabase;

    private function migration(): Migration
    {
        $migration = require database_path('migrations/2026_11_23_100000_drop_legacy_money_columns.php');
        assert($migration instanceof Migration);

        return $migration;
    }

    public function test_down_restores_the_decimal_columns_from_cents(): void
    {
        $product = Product::factory()->create(['unit_price' => '12.34']);
        $ticket = Ticket::factory()->create(['total_cents' => 1890]);

        $migration = $this->migration();

        try {
            $migration->down();

            $this->assertTrue(Schema::hasColumn('products', 'unit_price'));
            $this->assertTrue(Schema::hasColumn('tickets', 'total'));
            $this->assertEquals(12.34, (float) DB::table('products')->where('id', $product->id)->value('unit_price'));
            $this->assertEquals(18.90, (float) DB::table('tickets')->where('id', $ticket->id)->value('total'));
        } finally {
            $migration->up();
        }

        $this->assertFalse(Schema::hasColumn('products', 'unit_price'));
        $this->assertFalse(Schema::hasColumn('tickets', 'total'));
    }

    public function test_down_restores_the_original_column_definitions(): void
    {
        $season = Season::factory()->create();
        OperatingDay::factory()->create(['season_id' => $season->id]);

        $migration = $this->migration();

        try {
            $migration->down();

            $dayColumns = collect(Schema::getColumns('operating_days'))->keyBy('name');
            $this->assertFalse($dayColumns['total_cash']['nullable']);
            $this->assertEquals(0, (int) $dayColumns['total_cash']['default']);
            $this->assertTrue($dayColumns['closing_balance']['nullable']);

            $productColumn = collect(Schema::getColumns('products'))->keyBy('name')['unit_price'];
            $this->assertSame(Schema::getConnection()->getDriverName() === 'sqlite', $productColumn['nullable']);

            DB::table('operating_days')->insert([
                'season_id' => $season->id,
                'date' => '2030-01-01',
                'started_at' => now(),
            ]);

            $inserted = DB::table('operating_days')->where('date', '2030-01-01')->first();
            $this->assertEquals(0, (float) $inserted->total_cash);
            $this->assertNull($inserted->closing_balance);
        } finally {
            $migration->up();
        }
    }

    public function test_up_keeps_the_legacy_column_when_the_cents_sibling_is_missing(): void
    {
        $migration = $this->migration();

        try {
            $migration->down();

            Schema::table('products', function (Blueprint $table) {
                $table->dropColumn('unit_price_cents');
            });

            $migration->up();

            $this->assertTrue(Schema::hasColumn('products', 'unit_price'));
        } finally {
            Schema::table('products', function (Blueprint $table) {
                $table->bigInteger('unit_price_cents')->nullable();
            });

            $migration->up();
        }
    }
}
