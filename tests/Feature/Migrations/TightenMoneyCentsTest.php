<?php

namespace Tests\Feature\Migrations;

use App\Models\OperatingDay;
use App\Models\Ticket;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use RuntimeException;
use Tests\TestCase;

class TightenMoneyCentsTest extends TestCase
{
    use RefreshDatabase;

    private function migration(): Migration
    {
        $migration = require database_path('migrations/2026_11_23_100001_tighten_money_cents_columns.php');
        assert($migration instanceof Migration);

        return $migration;
    }

    public function test_it_fills_missing_operating_day_totals_with_zero(): void
    {
        $day = OperatingDay::factory()->create();

        DB::table('operating_days')->where('id', $day->id)->update([
            'total_cash_cents' => null,
            'total_twint_cents' => null,
        ]);

        $this->migration()->up();

        $row = DB::table('operating_days')->where('id', $day->id)->first();

        $this->assertSame(0, (int) $row->total_cash_cents);
        $this->assertSame(0, (int) $row->total_twint_cents);
    }

    public function test_it_fails_when_a_required_amount_is_missing(): void
    {
        $ticket = Ticket::factory()->create();

        DB::table('tickets')->where('id', $ticket->id)->update(['total_cents' => null]);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('tickets.total_cents');

        $this->migration()->up();
    }

    public function test_it_leaves_the_schema_untouched_on_sqlite(): void
    {
        $this->migration()->up();

        $column = collect(Schema::getColumns('tickets'))->keyBy('name')['total_cents'];

        $this->assertTrue($column['nullable']);
    }

    public function test_it_enforces_not_null_on_supported_drivers(): void
    {
        if (Schema::getConnection()->getDriverName() === 'sqlite') {
            $this->markTestSkipped('SQLite cannot alter column nullability without rebuilding the table.');
        }

        $this->migration()->up();

        $tickets = collect(Schema::getColumns('tickets'))->keyBy('name');
        $this->assertFalse($tickets['total_cents']['nullable']);

        $days = collect(Schema::getColumns('operating_days'))->keyBy('name');
        $this->assertFalse($days['total_cash_cents']['nullable']);
        $this->assertEquals(0, (int) $days['total_cash_cents']['default']);

        $vouchers = collect(Schema::getColumns('vouchers'))->keyBy('name');
        $this->assertTrue($vouchers['discount_fixed_cents']['nullable']);
    }

    public function test_down_makes_the_columns_nullable_again_on_supported_drivers(): void
    {
        if (Schema::getConnection()->getDriverName() === 'sqlite') {
            $this->markTestSkipped('SQLite cannot alter column nullability without rebuilding the table.');
        }

        $migration = $this->migration();

        $migration->up();
        $migration->down();

        $column = collect(Schema::getColumns('tickets'))->keyBy('name')['total_cents'];

        $this->assertTrue($column['nullable']);
    }
}
