<?php

namespace Tests\Unit\Services;

use App\Models\Category;
use App\Models\Product;
use App\Models\Season;
use App\Models\Voucher;
use App\Services\TicketCalculator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TicketCalculatorTest extends TestCase
{
    use RefreshDatabase;

    private TicketCalculator $calculator;

    protected function setUp(): void
    {
        parent::setUp();
        $this->calculator = new TicketCalculator;
    }

    private function makeProduct(float $unitPrice = 10.00): Product
    {
        $category = Category::factory()->create(['is_restricted' => false]);

        return Product::factory()->create([
            'category_id' => $category->id,
            'unit_price' => $unitPrice,
        ]);
    }

    private function makeSeason(): Season
    {
        return Season::factory()->create();
    }

    public function test_compute_without_vouchers_returns_single_row(): void
    {
        $product = $this->makeProduct(10.00);

        $result = $this->calculator->compute([
            ['product_id' => $product->id, 'quantity' => 3],
        ]);

        $this->assertEquals(30.00, $result['total']);
        $this->assertCount(1, $result['items']);
        $this->assertEquals([
            'product_id' => $product->id,
            'voucher_id' => null,
            'quantity' => 3,
            'unit_price' => 10.0,
            'subtotal' => 30.0,
        ], $result['items'][0]);
    }

    public function test_compute_empty_voucher_ids_behaves_like_no_voucher(): void
    {
        $product = $this->makeProduct(10.00);

        $result = $this->calculator->compute([
            ['product_id' => $product->id, 'quantity' => 2, 'voucher_ids' => []],
        ]);

        $this->assertEquals(20.00, $result['total']);
        $this->assertCount(1, $result['items']);
        $this->assertNull($result['items'][0]['voucher_id']);
        $this->assertEquals(2, $result['items'][0]['quantity']);
    }

    public function test_compute_percent_voucher_discounts_one_unit(): void
    {
        $season = $this->makeSeason();
        $product = $this->makeProduct(10.00);
        $voucher = Voucher::factory()->percent()->create([
            'discount_percent' => 10.00,
            'product_id' => $product->id,
            'season_id' => $season->id,
        ]);

        $result = $this->calculator->compute([
            ['product_id' => $product->id, 'quantity' => 3, 'voucher_ids' => [$voucher->id]],
        ]);

        $this->assertEquals(29.00, $result['total']);
        $this->assertCount(2, $result['items']);

        $voucherRow = $result['items'][0];
        $this->assertEquals($voucher->id, $voucherRow['voucher_id']);
        $this->assertEquals(1, $voucherRow['quantity']);
        $this->assertEquals(9.00, $voucherRow['subtotal']);

        $remainderRow = $result['items'][1];
        $this->assertNull($remainderRow['voucher_id']);
        $this->assertEquals(2, $remainderRow['quantity']);
        $this->assertEquals(20.00, $remainderRow['subtotal']);
    }

    public function test_compute_two_full_discount_vouchers_on_three_units(): void
    {
        $season = $this->makeSeason();
        $product = $this->makeProduct(10.00);
        $voucher1 = Voucher::factory()->percent()->create([
            'discount_percent' => 100.00,
            'product_id' => $product->id,
            'season_id' => $season->id,
        ]);
        $voucher2 = Voucher::factory()->percent()->create([
            'discount_percent' => 100.00,
            'product_id' => $product->id,
            'season_id' => $season->id,
        ]);

        $result = $this->calculator->compute([
            ['product_id' => $product->id, 'quantity' => 3, 'voucher_ids' => [$voucher1->id, $voucher2->id]],
        ]);

        $this->assertEquals(10.00, $result['total']);
        $this->assertCount(3, $result['items']);

        $this->assertEquals($voucher1->id, $result['items'][0]['voucher_id']);
        $this->assertEquals(0.00, $result['items'][0]['subtotal']);
        $this->assertEquals($voucher2->id, $result['items'][1]['voucher_id']);
        $this->assertEquals(0.00, $result['items'][1]['subtotal']);
        $this->assertNull($result['items'][2]['voucher_id']);
        $this->assertEquals(1, $result['items'][2]['quantity']);
        $this->assertEquals(10.00, $result['items'][2]['subtotal']);
    }

    public function test_compute_vouchers_covering_full_quantity_leave_no_remainder_row(): void
    {
        $season = $this->makeSeason();
        $product = $this->makeProduct(10.00);
        $voucher1 = Voucher::factory()->percent()->create([
            'discount_percent' => 100.00,
            'product_id' => $product->id,
            'season_id' => $season->id,
        ]);
        $voucher2 = Voucher::factory()->percent()->create([
            'discount_percent' => 100.00,
            'product_id' => $product->id,
            'season_id' => $season->id,
        ]);

        $result = $this->calculator->compute([
            ['product_id' => $product->id, 'quantity' => 2, 'voucher_ids' => [$voucher1->id, $voucher2->id]],
        ]);

        $this->assertEquals(0.00, $result['total']);
        $this->assertCount(2, $result['items']);
        foreach ($result['items'] as $row) {
            $this->assertEquals(1, $row['quantity']);
            $this->assertEquals(0.00, $row['subtotal']);
        }
    }

    public function test_compute_fixed_voucher_clamped_to_unit_price(): void
    {
        $season = $this->makeSeason();
        $product = $this->makeProduct(3.50);
        $voucher = Voucher::factory()->fixed()->create([
            'discount_fixed' => 10.00,
            'product_id' => $product->id,
            'season_id' => $season->id,
        ]);

        $result = $this->calculator->compute([
            ['product_id' => $product->id, 'quantity' => 1, 'voucher_ids' => [$voucher->id]],
        ]);

        $this->assertEquals(0.00, $result['total']);
        $this->assertEquals(0.00, $result['items'][0]['subtotal']);
    }

    public function test_compute_mixed_percent_and_fixed_vouchers(): void
    {
        $season = $this->makeSeason();
        $product = $this->makeProduct(10.00);
        $percentVoucher = Voucher::factory()->percent()->create([
            'discount_percent' => 100.00,
            'product_id' => $product->id,
            'season_id' => $season->id,
        ]);
        $fixedVoucher = Voucher::factory()->fixed()->create([
            'discount_fixed' => 2.00,
            'product_id' => $product->id,
            'season_id' => $season->id,
        ]);

        $result = $this->calculator->compute([
            ['product_id' => $product->id, 'quantity' => 2, 'voucher_ids' => [$percentVoucher->id, $fixedVoucher->id]],
        ]);

        $this->assertEquals(8.00, $result['total']);
        $this->assertCount(2, $result['items']);
        $this->assertEquals(0.00, $result['items'][0]['subtotal']);
        $this->assertEquals($percentVoucher->id, $result['items'][0]['voucher_id']);
        $this->assertEquals(8.00, $result['items'][1]['subtotal']);
        $this->assertEquals($fixedVoucher->id, $result['items'][1]['voucher_id']);
    }

    public function test_compute_uses_custom_unit_price_as_discount_base(): void
    {
        $season = $this->makeSeason();
        $product = $this->makeProduct(10.00);
        $voucher = Voucher::factory()->percent()->create([
            'discount_percent' => 10.00,
            'product_id' => $product->id,
            'season_id' => $season->id,
        ]);

        $result = $this->calculator->compute([
            [
                'product_id' => $product->id,
                'quantity' => 1,
                'custom_unit_price' => 7.50,
                'voucher_ids' => [$voucher->id],
            ],
        ]);

        $this->assertEquals(6.75, $result['total']);
        $this->assertEquals(6.75, $result['items'][0]['subtotal']);
    }

    public function test_compute_handles_multiple_items_independently(): void
    {
        $season = $this->makeSeason();
        $product1 = $this->makeProduct(10.00);
        $product2 = $this->makeProduct(5.00);
        $voucher = Voucher::factory()->percent()->create([
            'discount_percent' => 100.00,
            'product_id' => $product1->id,
            'season_id' => $season->id,
        ]);

        $result = $this->calculator->compute([
            ['product_id' => $product1->id, 'quantity' => 2, 'voucher_ids' => [$voucher->id]],
            ['product_id' => $product2->id, 'quantity' => 1],
        ]);

        $this->assertEquals(15.00, $result['total']);
        $this->assertCount(3, $result['items']);
        $this->assertEquals($voucher->id, $result['items'][0]['voucher_id']);
        $this->assertEquals(0.00, $result['items'][0]['subtotal']);
        $this->assertEquals(10.00, $result['items'][1]['subtotal']);
        $this->assertEquals(5.00, $result['items'][2]['subtotal']);
    }
}
