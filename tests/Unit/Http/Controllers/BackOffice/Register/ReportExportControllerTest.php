<?php

namespace Tests\Unit\Http\Controllers\BackOffice\Register;

use App\Http\Controllers\BackOffice\Register\ReportExportController;
use App\Models\Category;
use App\Models\Member;
use App\Models\OperatingDay;
use App\Models\Product;
use App\Models\Season;
use App\Models\Ticket;
use App\Models\TicketItem;
use App\Models\Voucher;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ReportExportControllerTest extends TestCase
{
    use RefreshDatabase;

    private ReportExportController $controller;

    protected function setUp(): void
    {
        parent::setUp();
        $this->controller = new ReportExportController;
    }

    public function test_balance_rows_contain_correct_data(): void
    {
        $season = Season::factory()->create([
            'start_date' => Carbon::now()->startOfYear(),
            'end_date' => Carbon::now()->endOfYear(),
            'initial_balance' => 0,
        ]);
        OperatingDay::factory()->create([
            'season_id' => $season->id,
            'date' => Carbon::parse('2026-03-15'),
            'total_cash' => 150.50,
            'total_twint' => 50.25,
            'closed_at' => Carbon::now(),
            'closing_balance' => 150.50,
        ]);

        $rows = iterator_to_array($this->controller->getRows($season, 'balance'));

        $this->assertCount(1, $rows);
        $this->assertEquals('15.03.2026', $rows[0]['Date']);
        $this->assertEquals('150,50', $rows[0]['Total Espèces']);
        $this->assertEquals('50,25', $rows[0]['Total Twint']);
        $this->assertEquals('150,50', $rows[0]['Solde Clôture']);
    }

    public function test_balance_rows_computed_closing_balance_when_open(): void
    {
        $season = Season::factory()->create([
            'start_date' => Carbon::now()->startOfYear(),
            'end_date' => Carbon::now()->endOfYear(),
            'initial_balance' => 100.00,
        ]);
        OperatingDay::factory()->create([
            'season_id' => $season->id,
            'total_cash' => 50.00,
            'closed_at' => null,
            'closing_balance' => null,
        ]);

        $rows = iterator_to_array($this->controller->getRows($season, 'balance'));

        $this->assertCount(1, $rows);
        $this->assertEquals('150,00', $rows[0]['Solde Clôture']);
    }

    public function test_ticket_rows_contain_correct_data(): void
    {
        $season = Season::factory()->create([
            'start_date' => Carbon::now()->startOfYear(),
            'end_date' => Carbon::now()->endOfYear(),
        ]);
        $day = OperatingDay::factory()->create([
            'season_id' => $season->id,
            'date' => Carbon::parse('2026-04-10'),
        ]);
        Ticket::factory()->cash()->create([
            'operating_day_id' => $day->id,
            'total' => 25.00,
            'sold_at' => Carbon::parse('2026-04-10 14:30:00'),
        ]);

        $rows = iterator_to_array($this->controller->getRows($season, 'tickets'));

        $this->assertCount(1, $rows);
        $this->assertEquals('10.04.2026', $rows[0]['Date']);
        // sold_at is stored as UTC (14:30 UTC), export displays as Zurich time (16:30 CEST)
        $this->assertEquals('16:30', $rows[0]['Vendu à']);
        $this->assertEquals('Espèces', $rows[0]['Paiement']);
        $this->assertEquals('25,00', $rows[0]['Total']);
    }

    public function test_ticket_rows_handle_twint_payment(): void
    {
        $season = Season::factory()->create([
            'start_date' => Carbon::now()->startOfYear(),
            'end_date' => Carbon::now()->endOfYear(),
        ]);
        $day = OperatingDay::factory()->create(['season_id' => $season->id]);
        Ticket::factory()->twint()->create([
            'operating_day_id' => $day->id,
            'total' => 15.00,
            'sold_at' => Carbon::now(),
        ]);

        $rows = iterator_to_array($this->controller->getRows($season, 'tickets'));

        $this->assertCount(1, $rows);
        $this->assertEquals('TWINT', $rows[0]['Paiement']);
    }

    public function test_ticket_rows_sorted_by_date(): void
    {
        $season = Season::factory()->create([
            'start_date' => Carbon::now()->startOfYear(),
            'end_date' => Carbon::now()->endOfYear(),
        ]);
        $day1 = OperatingDay::factory()->create([
            'season_id' => $season->id,
            'date' => Carbon::parse('2026-05-01'),
        ]);
        $day2 = OperatingDay::factory()->create([
            'season_id' => $season->id,
            'date' => Carbon::parse('2026-04-01'),
        ]);
        Ticket::factory()->cash()->create([
            'operating_day_id' => $day1->id,
            'sold_at' => Carbon::now(),
        ]);
        Ticket::factory()->cash()->create([
            'operating_day_id' => $day2->id,
            'sold_at' => Carbon::now(),
        ]);

        $rows = iterator_to_array($this->controller->getRows($season, 'tickets'));

        $this->assertCount(2, $rows);
        $this->assertEquals('01.04.2026', $rows[0]['Date']);
        $this->assertEquals('01.05.2026', $rows[1]['Date']);
    }

    public function test_item_rows_contain_product_data(): void
    {
        $season = Season::factory()->create([
            'start_date' => Carbon::now()->startOfYear(),
            'end_date' => Carbon::now()->endOfYear(),
        ]);
        $day = OperatingDay::factory()->create([
            'season_id' => $season->id,
            'date' => Carbon::parse('2026-06-15'),
        ]);
        $category = Category::factory()->create(['is_restricted' => false]);
        $product = Product::factory()->create([
            'category_id' => $category->id,
            'name' => 'Cartouche',
            'unit_price' => 0.50,
        ]);
        $ticket = Ticket::factory()->cash()->create([
            'operating_day_id' => $day->id,
            'sold_at' => Carbon::parse('2026-06-15 10:00:00'),
        ]);
        TicketItem::factory()->create([
            'ticket_id' => $ticket->id,
            'product_id' => $product->id,
            'quantity' => 10,
            'unit_price' => 0.50,
            'subtotal' => 5.00,
        ]);

        $rows = iterator_to_array($this->controller->getRows($season, 'items'));

        $this->assertCount(1, $rows);
        $this->assertEquals('15.06.2026', $rows[0]['Date']);
        $this->assertEquals('Cartouche', $rows[0]['Produit']);
        $this->assertEquals(10, $rows[0]['Quantité']);
        $this->assertEquals('0,50', $rows[0]['Prix Unitaire']);
        $this->assertEquals('5,00', $rows[0]['Sous-total']);
        $this->assertEquals('', $rows[0]['Réduction']);
    }

    public function test_item_rows_include_voucher_discount(): void
    {
        $season = Season::factory()->create([
            'start_date' => Carbon::now()->startOfYear(),
            'end_date' => Carbon::now()->endOfYear(),
        ]);
        $day = OperatingDay::factory()->create(['season_id' => $season->id]);
        $category = Category::factory()->create(['is_restricted' => false]);
        $product = Product::factory()->create([
            'category_id' => $category->id,
            'unit_price' => 10.00,
        ]);
        $member = Member::factory()->create();
        $voucher = Voucher::factory()->percent()->create([
            'discount_percent' => 10.00,
            'product_id' => $product->id,
            'season_id' => $season->id,
            'member_id' => $member->id,
        ]);
        $ticket = Ticket::factory()->cash()->create([
            'operating_day_id' => $day->id,
            'sold_at' => Carbon::now(),
        ]);
        TicketItem::factory()->create([
            'ticket_id' => $ticket->id,
            'product_id' => $product->id,
            'voucher_id' => $voucher->id,
            'quantity' => 1,
            'unit_price' => 10.00,
            'subtotal' => 9.00,
        ]);

        $rows = iterator_to_array($this->controller->getRows($season, 'items'));

        $this->assertCount(1, $rows);
        $this->assertEquals('1,00', $rows[0]['Réduction']);
    }

    public function test_item_rows_include_fixed_voucher_discount(): void
    {
        $season = Season::factory()->create([
            'start_date' => Carbon::now()->startOfYear(),
            'end_date' => Carbon::now()->endOfYear(),
        ]);
        $day = OperatingDay::factory()->create(['season_id' => $season->id]);
        $category = Category::factory()->create(['is_restricted' => false]);
        $product = Product::factory()->create([
            'category_id' => $category->id,
            'unit_price' => 15.00,
        ]);
        $member = Member::factory()->create();
        $voucher = Voucher::factory()->fixed()->create([
            'discount_fixed' => 5.00,
            'product_id' => $product->id,
            'season_id' => $season->id,
            'member_id' => $member->id,
        ]);
        $ticket = Ticket::factory()->cash()->create([
            'operating_day_id' => $day->id,
            'sold_at' => Carbon::now(),
        ]);
        TicketItem::factory()->create([
            'ticket_id' => $ticket->id,
            'product_id' => $product->id,
            'voucher_id' => $voucher->id,
            'quantity' => 1,
            'unit_price' => 15.00,
            'subtotal' => 10.00,
        ]);

        $rows = iterator_to_array($this->controller->getRows($season, 'items'));

        $this->assertCount(1, $rows);
        $this->assertEquals('5,00', $rows[0]['Réduction']);
    }

    public function test_balance_rows_empty_when_no_days(): void
    {
        $season = Season::factory()->create([
            'start_date' => Carbon::now()->startOfYear(),
            'end_date' => Carbon::now()->endOfYear(),
        ]);

        $rows = iterator_to_array($this->controller->getRows($season, 'balance'));

        $this->assertCount(0, $rows);
    }

    public function test_ticket_rows_empty_when_no_tickets(): void
    {
        $season = Season::factory()->create([
            'start_date' => Carbon::now()->startOfYear(),
            'end_date' => Carbon::now()->endOfYear(),
        ]);
        OperatingDay::factory()->create(['season_id' => $season->id]);

        $rows = iterator_to_array($this->controller->getRows($season, 'tickets'));

        $this->assertCount(0, $rows);
    }

    public function test_item_rows_empty_when_no_items(): void
    {
        $season = Season::factory()->create([
            'start_date' => Carbon::now()->startOfYear(),
            'end_date' => Carbon::now()->endOfYear(),
        ]);
        $day = OperatingDay::factory()->create(['season_id' => $season->id]);
        Ticket::factory()->cash()->create([
            'operating_day_id' => $day->id,
            'sold_at' => Carbon::now(),
        ]);

        $rows = iterator_to_array($this->controller->getRows($season, 'items'));

        $this->assertCount(0, $rows);
    }
}
