<?php

namespace Tests\Feature\Migrations;

use App\Models\Product;
use App\Models\Ticket;
use Illuminate\Database\Migrations\Migration;
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
}
