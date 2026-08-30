<?php

namespace Tests\Unit\Http\Controllers\BackOffice\Register;

use App\Http\Controllers\BackOffice\Register\VoucherController;
use App\Models\Category;
use App\Models\Member;
use App\Models\Product;
use App\Models\Season;
use App\Models\Voucher;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Response;
use Tests\TestCase;

class VoucherControllerTest extends TestCase
{
    use RefreshDatabase;

    private VoucherController $controller;

    protected function setUp(): void
    {
        parent::setUp();
        $this->controller = new VoucherController;
    }

    public function test_index_returns_inertia_response(): void
    {
        $category = Category::factory()->create(['is_restricted' => false]);
        $product = Product::factory()->create(['category_id' => $category->id]);
        $season = Season::factory()->create();
        Voucher::factory()->percent()->count(3)->create([
            'product_id' => $product->id,
            'season_id' => $season->id,
        ]);

        $response = $this->controller->index();

        $this->assertInstanceOf(Response::class, $response);
    }

    public function test_create_returns_inertia_response(): void
    {
        $response = $this->controller->create();

        $this->assertInstanceOf(Response::class, $response);
    }

    public function test_voucher_model_can_be_created(): void
    {
        $category = Category::factory()->create(['is_restricted' => false]);
        $product = Product::factory()->create(['category_id' => $category->id]);
        $season = Season::factory()->create();
        $member = Member::factory()->create();

        $voucher = Voucher::create([
            'discount_percent' => 15.00,
            'product_id' => $product->id,
            'season_id' => $season->id,
            'member_id' => $member->id,
        ]);

        $this->assertDatabaseHas('vouchers', [
            'id' => $voucher->id,
            'discount_percent' => 15.00,
        ]);
    }

    public function test_voucher_model_can_be_updated(): void
    {
        $voucher = Voucher::factory()->percent()->create();

        $voucher->update(['discount_percent' => 25.00]);

        $this->assertDatabaseHas('vouchers', ['id' => $voucher->id, 'discount_percent' => 25.00]);
    }

    public function test_voucher_model_soft_deletes(): void
    {
        $voucher = Voucher::factory()->percent()->create();

        $voucher->delete();

        $this->assertSoftDeleted('vouchers', ['id' => $voucher->id]);
    }
}
