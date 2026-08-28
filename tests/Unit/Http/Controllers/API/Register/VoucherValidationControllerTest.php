<?php

namespace Tests\Unit\Http\Controllers\API\Register;

use App\Http\Controllers\API\Register\VoucherValidationController;
use App\Http\Requests\API\Register\VoucherValidateRequest;
use App\Models\Category;
use App\Models\OperatingDay;
use App\Models\Product;
use App\Models\Season;
use App\Models\Ticket;
use App\Models\TicketItem;
use App\Models\Voucher;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class VoucherValidationControllerTest extends TestCase
{
    use RefreshDatabase;

    private VoucherValidationController $controller;

    protected function setUp(): void
    {
        parent::setUp();
        $this->controller = new VoucherValidationController;
    }

    private function makeRequest(array $data): VoucherValidateRequest
    {
        $request = new VoucherValidateRequest;
        $request->merge($data);

        return $request;
    }

    public function test_validate_returns_valid_for_percent_voucher(): void
    {
        $category = Category::factory()->create(['is_restricted' => false]);
        $product = Product::factory()->create(['category_id' => $category->id]);
        $season = Season::factory()->create([
            'start_date' => Carbon::now()->subMonth(),
            'end_date' => Carbon::now()->addMonth(),
        ]);
        $voucher = Voucher::factory()->percent()->create([
            'code' => 'TEST10',
            'product_id' => $product->id,
            'season_id' => $season->id,
        ]);

        $response = $this->controller->validate($this->makeRequest([
            'code' => 'TEST10',
            'product_id' => $product->id,
            'season_id' => $season->id,
        ]));

        $data = json_decode($response->getContent(), true);
        $this->assertEquals(200, $response->status());
        $this->assertTrue($data['data']['valid']);
        $this->assertEquals('percent', $data['data']['type']);
        $this->assertEquals((float) $voucher->discount_percent, $data['data']['value']);
    }

    public function test_validate_returns_valid_for_fixed_voucher(): void
    {
        $category = Category::factory()->create(['is_restricted' => false]);
        $product = Product::factory()->create(['category_id' => $category->id]);
        $season = Season::factory()->create([
            'start_date' => Carbon::now()->subMonth(),
            'end_date' => Carbon::now()->addMonth(),
        ]);
        $voucher = Voucher::factory()->fixed()->create([
            'code' => 'FIX5',
            'product_id' => $product->id,
            'season_id' => $season->id,
        ]);

        $response = $this->controller->validate($this->makeRequest([
            'code' => 'FIX5',
            'product_id' => $product->id,
            'season_id' => $season->id,
        ]));

        $data = json_decode($response->getContent(), true);
        $this->assertEquals(200, $response->status());
        $this->assertTrue($data['data']['valid']);
        $this->assertEquals('fixed', $data['data']['type']);
        $this->assertEquals((float) $voucher->discount_fixed, $data['data']['value']);
    }

    public function test_validate_returns_invalid_for_used_voucher(): void
    {
        $category = Category::factory()->create(['is_restricted' => false]);
        $product = Product::factory()->create(['category_id' => $category->id]);
        $season = Season::factory()->create([
            'start_date' => Carbon::now()->subMonth(),
            'end_date' => Carbon::now()->addMonth(),
        ]);
        $voucher = Voucher::factory()->percent()->create([
            'code' => 'USED',
            'product_id' => $product->id,
            'season_id' => $season->id,
        ]);

        $day = OperatingDay::factory()->create(['season_id' => $season->id]);
        $ticket = Ticket::factory()->cash()->create(['operating_day_id' => $day->id]);
        TicketItem::factory()->create([
            'ticket_id' => $ticket->id,
            'product_id' => $product->id,
            'voucher_id' => $voucher->id,
        ]);

        $response = $this->controller->validate($this->makeRequest([
            'code' => 'USED',
            'product_id' => $product->id,
            'season_id' => $season->id,
        ]));

        $data = json_decode($response->getContent(), true);
        $this->assertEquals(200, $response->status());
        $this->assertFalse($data['data']['valid']);
        $this->assertArrayHasKey('error', $data['data']);
    }

    public function test_validate_returns_invalid_for_wrong_product(): void
    {
        $category = Category::factory()->create(['is_restricted' => false]);
        $product = Product::factory()->create(['category_id' => $category->id]);
        $otherProduct = Product::factory()->create(['category_id' => $category->id]);
        $season = Season::factory()->create([
            'start_date' => Carbon::now()->subMonth(),
            'end_date' => Carbon::now()->addMonth(),
        ]);
        Voucher::factory()->percent()->create([
            'code' => 'WRONG',
            'product_id' => $product->id,
            'season_id' => $season->id,
        ]);

        $response = $this->controller->validate($this->makeRequest([
            'code' => 'WRONG',
            'product_id' => $otherProduct->id,
            'season_id' => $season->id,
        ]));

        $data = json_decode($response->getContent(), true);
        $this->assertEquals(200, $response->status());
        $this->assertFalse($data['data']['valid']);
    }

    public function test_validate_returns_invalid_for_wrong_season(): void
    {
        $category = Category::factory()->create(['is_restricted' => false]);
        $product = Product::factory()->create(['category_id' => $category->id]);
        $season = Season::factory()->create([
            'start_date' => Carbon::now()->subMonth(),
            'end_date' => Carbon::now()->addMonth(),
        ]);
        $otherSeason = Season::factory()->create([
            'start_date' => Carbon::now()->subMonth(),
            'end_date' => Carbon::now()->addMonth(),
        ]);
        Voucher::factory()->percent()->create([
            'code' => 'WRONGSEASON',
            'product_id' => $product->id,
            'season_id' => $season->id,
        ]);

        $response = $this->controller->validate($this->makeRequest([
            'code' => 'WRONGSEASON',
            'product_id' => $product->id,
            'season_id' => $otherSeason->id,
        ]));

        $data = json_decode($response->getContent(), true);
        $this->assertEquals(200, $response->status());
        $this->assertFalse($data['data']['valid']);
    }

    public function test_validate_returns_invalid_for_inactive_season(): void
    {
        $category = Category::factory()->create(['is_restricted' => false]);
        $product = Product::factory()->create(['category_id' => $category->id]);
        $season = Season::factory()->create([
            'start_date' => Carbon::now()->subYear(),
            'end_date' => Carbon::now()->subMonth(),
        ]);
        Voucher::factory()->percent()->create([
            'code' => 'EXPIRED',
            'product_id' => $product->id,
            'season_id' => $season->id,
        ]);

        $response = $this->controller->validate($this->makeRequest([
            'code' => 'EXPIRED',
            'product_id' => $product->id,
            'season_id' => $season->id,
        ]));

        $data = json_decode($response->getContent(), true);
        $this->assertEquals(200, $response->status());
        $this->assertFalse($data['data']['valid']);
    }
}
