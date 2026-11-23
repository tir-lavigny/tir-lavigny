<?php

namespace Tests\Feature\Migrations;

use App\Models\Product;
use App\Models\Ticket;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class FinalizeMoneyCentsTest extends TestCase
{
    use RefreshDatabase;

    private function migration(): Migration
    {
        $migration = require database_path('migrations/2026_11_23_099999_finalize_money_cents_columns.php');
        assert($migration instanceof Migration);

        return $migration;
    }

    /**
     * The final safety pass reads the legacy decimal columns, which are already
     * dropped by the phase-2 migration, so they are re-added for the test.
     */
    private function addLegacyColumns(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->decimal('unit_price', 10, 2)->nullable();
        });

        Schema::table('tickets', function (Blueprint $table) {
            $table->decimal('total', 10, 2)->nullable();
        });
    }

    private function dropLegacyColumns(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropColumn('unit_price');
        });

        Schema::table('tickets', function (Blueprint $table) {
            $table->dropColumn('total');
        });
    }

    public function test_it_backfills_cents_that_bypassed_the_dual_write(): void
    {
        $product = Product::factory()->create();
        $ticket = Ticket::factory()->create();

        $this->addLegacyColumns();

        try {
            DB::table('products')->where('id', $product->id)->update([
                'unit_price' => '99.99',
                'unit_price_cents' => null,
            ]);
            DB::table('tickets')->where('id', $ticket->id)->update([
                'total' => '18.90',
                'total_cents' => null,
            ]);

            $this->migration()->up();

            $this->assertSame(9999, (int) DB::table('products')->where('id', $product->id)->value('unit_price_cents'));
            $this->assertSame(1890, (int) DB::table('tickets')->where('id', $ticket->id)->value('total_cents'));
        } finally {
            $this->dropLegacyColumns();
        }
    }

    public function test_it_never_overwrites_existing_cents(): void
    {
        $product = Product::factory()->create();

        $this->addLegacyColumns();

        try {
            DB::table('products')->where('id', $product->id)->update([
                'unit_price' => '99.99',
                'unit_price_cents' => 4321,
            ]);

            $this->migration()->up();

            $this->assertSame(4321, (int) DB::table('products')->where('id', $product->id)->value('unit_price_cents'));
        } finally {
            $this->dropLegacyColumns();
        }
    }
}
