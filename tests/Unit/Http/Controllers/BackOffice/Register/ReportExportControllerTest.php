<?php

namespace Tests\Unit\Http\Controllers\BackOffice\Register;

use App\Http\Controllers\BackOffice\Register\ReportExportController;
use App\Models\Category;
use App\Models\Member;
use App\Models\OperatingDay;
use App\Models\Product;
use App\Models\Season;
use App\Models\StockLocation;
use App\Models\StockMovement;
use App\Models\StockSnapshot;
use App\Models\StockSnapshotLine;
use App\Models\Ticket;
use App\Models\TicketItem;
use App\Models\Voucher;
use App\Services\StockReportService;
use App\Services\StockService;
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
        $this->controller = new ReportExportController(
            new StockReportService(new StockService),
        );
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

    public function test_stock_rows_contain_correct_data(): void
    {
        $season = Season::factory()->create([
            'start_date' => Carbon::parse('2026-01-01'),
            'end_date' => Carbon::parse('2026-03-31'),
        ]);
        $location = StockLocation::factory()->create(['name' => 'Bar', 'active' => true]);
        $product = Product::factory()->create(['name' => 'Bière', 'is_stockable' => true]);

        StockMovement::factory()->create([
            'stock_location_id' => $location->id,
            'product_id' => $product->id,
            'season_id' => $season->id,
            'quantity' => 50,
            'type' => 'purchase',
            'created_at' => Carbon::parse('2026-02-01 10:00:00'),
        ]);
        StockMovement::factory()->sale()->create([
            'stock_location_id' => $location->id,
            'product_id' => $product->id,
            'season_id' => $season->id,
            'quantity' => -10,
            'created_at' => Carbon::parse('2026-02-15 12:00:00'),
        ]);

        $rows = iterator_to_array($this->controller->getRows($season, 'stock'));

        $this->assertCount(1, $rows);
        $this->assertEquals('Bar', $rows[0]['Lieu']);
        $this->assertEquals('Bière', $rows[0]['Produit']);
        $this->assertEquals(40, $rows[0]['Stock actuel']);
        $this->assertEquals(10, $rows[0]['Ventes']);
        $this->assertEquals(0, $rows[0]['Pertes']);
        $this->assertEquals(0, $rows[0]['Entrées transferts']);
        $this->assertEquals(0, $rows[0]['Sorties transferts']);
        $this->assertEquals(0, $rows[0]['Ajustements']);
        $this->assertEquals(0, $rows[0]['Retours']);
        $this->assertEquals(0, $rows[0]['Réversions']);
        $this->assertEquals(40, $rows[0]['Variation saison']);
    }

    public function test_stock_rows_includes_zero_activity_products(): void
    {
        $season = Season::factory()->create([
            'start_date' => Carbon::parse('2026-01-01'),
            'end_date' => Carbon::parse('2026-03-31'),
        ]);
        $location = StockLocation::factory()->create(['name' => 'Bar', 'active' => true]);
        Product::factory()->create(['name' => 'Bière', 'is_stockable' => true]);
        Product::factory()->create(['name' => 'Soda', 'is_stockable' => true]);

        $rows = iterator_to_array($this->controller->getRows($season, 'stock'));

        $this->assertCount(2, $rows);
        $this->assertEquals(0, $rows[0]['Stock actuel']);
        $this->assertEquals(0, $rows[0]['Ventes']);
        $this->assertEquals(0, $rows[1]['Stock actuel']);
        $this->assertEquals(0, $rows[1]['Ventes']);
    }

    public function test_stock_rows_excludes_non_stockable_products(): void
    {
        $season = Season::factory()->create([
            'start_date' => Carbon::parse('2026-01-01'),
            'end_date' => Carbon::parse('2026-03-31'),
        ]);
        $location = StockLocation::factory()->create(['active' => true]);
        Product::factory()->create(['name' => 'Bière', 'is_stockable' => true]);
        Product::factory()->create(['name' => 'Ticket', 'is_stockable' => false]);

        $rows = iterator_to_array($this->controller->getRows($season, 'stock'));

        $this->assertCount(1, $rows);
        $this->assertEquals('Bière', $rows[0]['Produit']);
    }

    public function test_stock_rows_multiple_locations(): void
    {
        $season = Season::factory()->create([
            'start_date' => Carbon::parse('2026-01-01'),
            'end_date' => Carbon::parse('2026-03-31'),
        ]);
        $bar = StockLocation::factory()->create(['name' => 'Bar', 'active' => true]);
        $cave = StockLocation::factory()->create(['name' => 'Cave', 'active' => true]);
        $product = Product::factory()->create(['name' => 'Bière', 'is_stockable' => true]);

        StockMovement::factory()->create([
            'stock_location_id' => $bar->id,
            'product_id' => $product->id,
            'season_id' => $season->id,
            'quantity' => 30,
            'type' => 'purchase',
            'created_at' => Carbon::parse('2026-01-15 10:00:00'),
        ]);
        StockMovement::factory()->create([
            'stock_location_id' => $cave->id,
            'product_id' => $product->id,
            'season_id' => $season->id,
            'quantity' => 20,
            'type' => 'purchase',
            'created_at' => Carbon::parse('2026-02-01 10:00:00'),
        ]);

        $rows = iterator_to_array($this->controller->getRows($season, 'stock'));

        $this->assertCount(2, $rows);
        $this->assertEquals('Bar', $rows[0]['Lieu']);
        $this->assertEquals(30, $rows[0]['Stock actuel']);
        $this->assertEquals('Cave', $rows[1]['Lieu']);
        $this->assertEquals(20, $rows[1]['Stock actuel']);
    }

    public function test_stock_rows_with_transfers_split_incoming_outgoing(): void
    {
        $season = Season::factory()->create([
            'start_date' => Carbon::parse('2026-01-01'),
            'end_date' => Carbon::parse('2026-03-31'),
        ]);
        $bar = StockLocation::factory()->create(['name' => 'Bar', 'active' => true]);
        $cave = StockLocation::factory()->create(['name' => 'Cave', 'active' => true]);
        $product = Product::factory()->create(['name' => 'Bière', 'is_stockable' => true]);

        // Transfer from Bar to Cave: negative at Bar, positive at Cave
        StockMovement::factory()->create([
            'stock_location_id' => $bar->id,
            'product_id' => $product->id,
            'season_id' => $season->id,
            'quantity' => -10,
            'type' => 'transfer',
            'created_at' => Carbon::parse('2026-01-20 14:00:00'),
        ]);
        StockMovement::factory()->create([
            'stock_location_id' => $cave->id,
            'product_id' => $product->id,
            'season_id' => $season->id,
            'quantity' => 10,
            'type' => 'transfer',
            'created_at' => Carbon::parse('2026-01-20 14:00:00'),
        ]);

        $rows = iterator_to_array($this->controller->getRows($season, 'stock'));

        $barRow = collect($rows)->firstWhere('Lieu', 'Bar');
        $caveRow = collect($rows)->firstWhere('Lieu', 'Cave');

        $this->assertEquals(10, $barRow['Sorties transferts']);
        $this->assertEquals(0, $barRow['Entrées transferts']);
        $this->assertEquals(10, $caveRow['Entrées transferts']);
        $this->assertEquals(0, $caveRow['Sorties transferts']);
    }

    public function test_stock_rows_season_filter_uses_created_at_not_season_id(): void
    {
        $season = Season::factory()->create([
            'start_date' => Carbon::parse('2026-01-01'),
            'end_date' => Carbon::parse('2026-03-31'),
        ]);
        $location = StockLocation::factory()->create(['active' => true]);
        $product = Product::factory()->create(['is_stockable' => true]);

        // Movement inside season date range but with season_id = null
        StockMovement::factory()->withoutSeason()->create([
            'stock_location_id' => $location->id,
            'product_id' => $product->id,
            'quantity' => 15,
            'type' => 'purchase',
            'created_at' => Carbon::parse('2026-02-10 10:00:00'),
        ]);

        $rows = iterator_to_array($this->controller->getRows($season, 'stock'));

        $this->assertCount(1, $rows);
        $this->assertEquals(15, $rows[0]['Variation saison']);
        $this->assertEquals(15, $rows[0]['Stock actuel']);
    }

    public function test_stock_rows_ignores_movements_outside_season(): void
    {
        $season = Season::factory()->create([
            'start_date' => Carbon::parse('2026-01-01'),
            'end_date' => Carbon::parse('2026-03-31'),
        ]);
        $location = StockLocation::factory()->create(['active' => true]);
        $product = Product::factory()->create(['is_stockable' => true]);

        // Movement outside season
        StockMovement::factory()->create([
            'stock_location_id' => $location->id,
            'product_id' => $product->id,
            'season_id' => $season->id,
            'quantity' => 50,
            'type' => 'purchase',
            'created_at' => Carbon::parse('2025-12-01 10:00:00'),
        ]);

        $rows = iterator_to_array($this->controller->getRows($season, 'stock'));

        $this->assertCount(1, $rows);
        $this->assertEquals(50, $rows[0]['Stock actuel']);
        $this->assertEquals(0, $rows[0]['Variation saison']);
    }

    public function test_stock_rows_uses_current_snapshot(): void
    {
        $season = Season::factory()->create([
            'start_date' => Carbon::parse('2026-01-01'),
            'end_date' => Carbon::parse('2026-03-31'),
        ]);
        $location = StockLocation::factory()->create(['active' => true]);
        $product = Product::factory()->create(['is_stockable' => true]);

        // Create movements before snapshot
        StockMovement::factory()->create([
            'stock_location_id' => $location->id,
            'product_id' => $product->id,
            'season_id' => $season->id,
            'quantity' => 100,
            'type' => 'purchase',
            'created_at' => Carbon::parse('2026-01-05 10:00:00'),
        ]);
        StockMovement::factory()->sale()->create([
            'stock_location_id' => $location->id,
            'product_id' => $product->id,
            'season_id' => $season->id,
            'quantity' => -20,
            'created_at' => Carbon::parse('2026-01-10 12:00:00'),
        ]);

        // Create snapshot with value 80
        $snapshot = StockSnapshot::factory()->create([
            'stock_location_id' => $location->id,
            'snapshot_at' => Carbon::parse('2026-01-15 00:00:00'),
            'created_at' => Carbon::parse('2026-01-15 00:00:00'),
        ]);
        StockSnapshotLine::factory()->create([
            'stock_snapshot_id' => $snapshot->id,
            'product_id' => $product->id,
            'quantity' => 80,
        ]);

        // Movement after snapshot
        StockMovement::factory()->create([
            'stock_location_id' => $location->id,
            'product_id' => $product->id,
            'season_id' => $season->id,
            'quantity' => 10,
            'type' => 'purchase',
            'created_at' => Carbon::parse('2026-01-20 10:00:00'),
        ]);

        $rows = iterator_to_array($this->controller->getRows($season, 'stock'));

        $this->assertCount(1, $rows);
        $this->assertEquals(90, $rows[0]['Stock actuel']);
    }

    public function test_stock_movements_rows_contain_correct_data(): void
    {
        $season = Season::factory()->create([
            'start_date' => Carbon::parse('2026-01-01'),
            'end_date' => Carbon::parse('2026-03-31'),
        ]);
        $location = StockLocation::factory()->create(['name' => 'Bar']);
        $product = Product::factory()->create(['name' => 'Bière']);

        StockMovement::factory()->create([
            'stock_location_id' => $location->id,
            'product_id' => $product->id,
            'season_id' => $season->id,
            'quantity' => 50,
            'type' => 'purchase',
            'created_at' => Carbon::parse('2026-02-01 14:30:00'),
            'reason' => 'Livraison',
        ]);

        $rows = iterator_to_array($this->controller->getRows($season, 'stock-movements'));

        $this->assertCount(1, $rows);
        $this->assertEquals('01.02.2026', $rows[0]['Date']);
        $this->assertEquals('15:30', $rows[0]['Heure']);
        $this->assertEquals('Bar', $rows[0]['Lieu']);
        $this->assertEquals('Bière', $rows[0]['Produit']);
        $this->assertEquals('Achat', $rows[0]['Type']);
        $this->assertEquals(50, $rows[0]['Quantité']);
        $this->assertEquals('Livraison', $rows[0]['Motif']);
        $this->assertEquals('', $rows[0]['Référence']);
    }

    public function test_stock_movements_rows_sorted_by_created_at(): void
    {
        $season = Season::factory()->create([
            'start_date' => Carbon::parse('2026-01-01'),
            'end_date' => Carbon::parse('2026-03-31'),
        ]);
        $location = StockLocation::factory()->create();
        $product = Product::factory()->create();

        StockMovement::factory()->create([
            'stock_location_id' => $location->id,
            'product_id' => $product->id,
            'season_id' => $season->id,
            'quantity' => 10,
            'type' => 'purchase',
            'created_at' => Carbon::parse('2026-02-15 10:00:00'),
        ]);
        StockMovement::factory()->create([
            'stock_location_id' => $location->id,
            'product_id' => $product->id,
            'season_id' => $season->id,
            'quantity' => 5,
            'type' => 'purchase',
            'created_at' => Carbon::parse('2026-02-01 10:00:00'),
        ]);

        $rows = iterator_to_array($this->controller->getRows($season, 'stock-movements'));

        $this->assertCount(2, $rows);
        $this->assertEquals('01.02.2026', $rows[0]['Date']);
        $this->assertEquals('15.02.2026', $rows[1]['Date']);
    }

    public function test_stock_movements_rows_uses_date_range_not_season_id(): void
    {
        $season = Season::factory()->create([
            'start_date' => Carbon::parse('2026-01-01'),
            'end_date' => Carbon::parse('2026-03-31'),
        ]);
        $location = StockLocation::factory()->create();
        $product = Product::factory()->create();

        // Movement inside season date range but with season_id = null
        StockMovement::factory()->withoutSeason()->create([
            'stock_location_id' => $location->id,
            'product_id' => $product->id,
            'quantity' => 20,
            'type' => 'purchase',
            'created_at' => Carbon::parse('2026-02-10 10:00:00'),
        ]);

        // Movement outside season date range but with season_id = null
        StockMovement::factory()->withoutSeason()->create([
            'stock_location_id' => $location->id,
            'product_id' => $product->id,
            'quantity' => 30,
            'type' => 'purchase',
            'created_at' => Carbon::parse('2025-12-15 10:00:00'),
        ]);

        $rows = iterator_to_array($this->controller->getRows($season, 'stock-movements'));

        $this->assertCount(1, $rows);
        $this->assertEquals(20, $rows[0]['Quantité']);
    }

    public function test_stock_movements_rows_ignores_outside_season(): void
    {
        $season = Season::factory()->create([
            'start_date' => Carbon::parse('2026-01-01'),
            'end_date' => Carbon::parse('2026-03-31'),
        ]);
        $location = StockLocation::factory()->create();
        $product = Product::factory()->create();

        StockMovement::factory()->create([
            'stock_location_id' => $location->id,
            'product_id' => $product->id,
            'season_id' => $season->id,
            'quantity' => 10,
            'type' => 'purchase',
            'created_at' => Carbon::parse('2025-12-01 10:00:00'),
        ]);

        $rows = iterator_to_array($this->controller->getRows($season, 'stock-movements'));

        $this->assertCount(0, $rows);
    }

    public function test_stock_movements_rows_shows_ticket_reference(): void
    {
        $season = Season::factory()->create([
            'start_date' => Carbon::parse('2026-01-01'),
            'end_date' => Carbon::parse('2026-03-31'),
        ]);
        $location = StockLocation::factory()->create();
        $product = Product::factory()->create();
        $ticket = Ticket::factory()->cash()->create();

        StockMovement::factory()->create([
            'stock_location_id' => $location->id,
            'product_id' => $product->id,
            'season_id' => $season->id,
            'quantity' => -5,
            'type' => 'sale',
            'reference_type' => Ticket::class,
            'reference_id' => $ticket->id,
            'created_at' => Carbon::parse('2026-02-01 10:00:00'),
        ]);

        $rows = iterator_to_array($this->controller->getRows($season, 'stock-movements'));

        $this->assertCount(1, $rows);
        $this->assertEquals("Ticket #{$ticket->id}", $rows[0]['Référence']);
    }

    public function test_existing_balance_ticker_item_exports_continue_to_work(): void
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

        $balanceRows = iterator_to_array($this->controller->getRows($season, 'balance'));
        $this->assertCount(1, $balanceRows);
        $this->assertEquals('150,50', $balanceRows[0]['Total Espèces']);
    }
}
