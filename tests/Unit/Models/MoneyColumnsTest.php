<?php

namespace Tests\Unit\Models;

use App\Models\CashMovement;
use App\Models\Category;
use App\Models\OperatingDay;
use App\Models\Product;
use App\Models\Season;
use App\Models\Ticket;
use App\Models\TicketItem;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class MoneyColumnsTest extends TestCase
{
    use RefreshDatabase;

    private function product(): Product
    {
        return Product::factory()->create([
            'category_id' => Category::factory()->create(['is_restricted' => false])->id,
        ]);
    }

    public function test_legacy_decimal_money_columns_are_gone(): void
    {
        $this->assertFalse(Schema::hasColumn('products', 'unit_price'));
        $this->assertFalse(Schema::hasColumn('price_list_items', 'unit_price'));
        $this->assertFalse(Schema::hasColumn('vouchers', 'discount_fixed'));
        $this->assertFalse(Schema::hasColumn('seasons', 'initial_balance'));
        $this->assertFalse(Schema::hasColumn('seasons', 'end_balance'));
        $this->assertFalse(Schema::hasColumn('operating_days', 'closing_balance'));
        $this->assertFalse(Schema::hasColumn('operating_days', 'total_cash'));
        $this->assertFalse(Schema::hasColumn('operating_days', 'total_twint'));
        $this->assertFalse(Schema::hasColumn('cash_movements', 'amount'));
        $this->assertFalse(Schema::hasColumn('tickets', 'total'));
        $this->assertFalse(Schema::hasColumn('ticket_items', 'unit_price'));
        $this->assertFalse(Schema::hasColumn('ticket_items', 'subtotal'));
    }

    public function test_writing_a_decimal_amount_stores_cents_only(): void
    {
        $product = $this->product();

        $product->update(['unit_price' => '12.34']);

        $this->assertSame(1234, $product->unit_price_cents);
        $this->assertSame('12.34', $product->unit_price);
        $this->assertSame(1234, (int) DB::table('products')->where('id', $product->id)->value('unit_price_cents'));
    }

    public function test_writing_cents_exposes_the_decimal_attribute(): void
    {
        $ticket = Ticket::factory()->create(['total_cents' => 1050]);

        $this->assertSame(1050, $ticket->total_cents);
        $this->assertSame('10.50', $ticket->total);
    }

    public function test_a_null_amount_stays_null(): void
    {
        $season = Season::factory()->create(['initial_balance' => null]);

        $this->assertNull($season->initial_balance_cents);
        $this->assertNull($season->initial_balance);
    }

    public function test_negative_cash_movements_keep_their_sign(): void
    {
        $season = Season::factory()->create();

        $movement = CashMovement::factory()->create([
            'season_id' => $season->id,
            'amount' => -42.75,
        ]);

        $this->assertSame(-4275, $movement->amount_cents);
        $this->assertSame('-42.75', $movement->amount);
        $this->assertTrue($movement->isWithdrawal());
    }

    public function test_operating_day_totals_are_stored_as_cents(): void
    {
        $day = OperatingDay::factory()->create();

        $day->update([
            'total_cash' => '35.50',
            'total_twint' => '80.00',
            'closing_balance' => '115.50',
        ]);

        $fresh = $day->fresh();

        $this->assertSame(3550, $fresh->total_cash_cents);
        $this->assertSame(8000, $fresh->total_twint_cents);
        $this->assertSame(11550, $fresh->closing_balance_cents);
        $this->assertSame('35.50', (string) $fresh->total_cash);
        $this->assertSame(115.5, $fresh->closing_balance);
    }

    public function test_ticket_items_store_unit_price_and_subtotal_as_cents(): void
    {
        $ticket = Ticket::factory()->create();
        $product = $this->product();

        $item = TicketItem::factory()->create([
            'ticket_id' => $ticket->id,
            'product_id' => $product->id,
            'quantity' => 3,
            'unit_price' => '2.25',
            'subtotal' => '6.75',
        ]);

        $this->assertSame(225, $item->unit_price_cents);
        $this->assertSame(675, $item->subtotal_cents);
        $this->assertSame('2.25', $item->unit_price);
        $this->assertSame('6.75', $item->subtotal);
    }

    public function test_season_initial_balance_is_stored_as_cents(): void
    {
        $season = Season::factory()->create(['initial_balance' => '250.10']);

        $this->assertSame(25010, $season->initial_balance_cents);
        $this->assertSame('250.10', $season->initial_balance);
    }

    public function test_cents_columns_are_hidden_from_serialization(): void
    {
        $ticket = Ticket::factory()->create(['total_cents' => 1050]);

        $array = $ticket->toArray();

        $this->assertArrayNotHasKey('total_cents', $array);
        $this->assertSame('10.50', $array['total']);
        $this->assertSame(1050, $ticket->total_cents);
    }

    public function test_string_cents_from_a_driver_are_returned_as_integers(): void
    {
        $ticket = Ticket::factory()->create(['total_cents' => 1050]);

        // Some drivers / PDO settings hand back numeric columns as strings.
        DB::table('tickets')->where('id', $ticket->id)->update(['total_cents' => '1050']);

        $fresh = $ticket->fresh();

        $this->assertSame(1050, $fresh->total_cents);
        $this->assertSame(1050, (int) $fresh->getRawOriginal('total_cents'));
        $this->assertSame('10.50', $fresh->total);
    }
}
