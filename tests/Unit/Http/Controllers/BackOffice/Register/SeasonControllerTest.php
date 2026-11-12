<?php

namespace Tests\Unit\Http\Controllers\BackOffice\Register;

use App\Http\Controllers\BackOffice\Register\SeasonController;
use App\Models\Category;
use App\Models\Member;
use App\Models\Product;
use App\Models\Season;
use App\Services\SeasonVoucherService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Response;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

class SeasonControllerTest extends TestCase
{
    use RefreshDatabase;

    private SeasonController $controller;

    protected function setUp(): void
    {
        parent::setUp();
        $this->controller = new SeasonController;
    }

    public function test_index_returns_inertia_response(): void
    {
        Season::factory()->count(3)->create();

        $response = $this->controller->index(new Request);

        $this->assertInstanceOf(Response::class, $response);
    }

    public function test_create_returns_inertia_response(): void
    {
        $response = $this->controller->create();

        $this->assertInstanceOf(Response::class, $response);
    }

    public function test_show_returns_inertia_response(): void
    {
        $season = Season::factory()->create();

        $response = $this->controller->show($season);

        $this->assertInstanceOf(Response::class, $response);
    }

    public function test_season_model_can_be_created(): void
    {
        $season = Season::create([
            'name' => '2026-2027',
            'start_date' => Carbon::now()->startOfYear(),
            'end_date' => Carbon::now()->endOfYear(),
            'initial_balance' => 500.00,
        ]);

        $this->assertDatabaseHas('seasons', ['name' => '2026-2027']);
    }

    public function test_season_model_can_be_updated(): void
    {
        $season = Season::factory()->create(['name' => 'Old']);

        $season->update(['name' => 'New']);

        $this->assertDatabaseHas('seasons', ['id' => $season->id, 'name' => 'New']);
    }

    public function test_season_model_soft_deletes(): void
    {
        $season = Season::factory()->create();

        $season->delete();

        $this->assertSoftDeleted('seasons', ['id' => $season->id]);
    }

    public function test_preview_base_vouchers_returns_json_response(): void
    {
        $this->createBasePackageProducts();
        $season = Season::factory()->create();
        Member::factory()->count(2)->create();

        $response = $this->controller->previewBaseVouchers($season, app(SeasonVoucherService::class));

        $this->assertInstanceOf(JsonResponse::class, $response);
    }

    public function test_generate_base_vouchers_returns_redirect_response(): void
    {
        $this->createBasePackageProducts();
        $season = Season::factory()->create();
        Member::factory()->count(2)->create();

        $response = $this->controller->generateBaseVouchers($season, app(SeasonVoucherService::class));

        $this->assertInstanceOf(RedirectResponse::class, $response);
    }

    public function test_preview_base_vouchers_throws_when_product_missing(): void
    {
        $season = Season::factory()->create();

        $this->expectException(HttpException::class);
        $this->controller->previewBaseVouchers($season, app(SeasonVoucherService::class));
    }

    public function test_generate_base_vouchers_throws_when_product_missing(): void
    {
        $season = Season::factory()->create();

        $this->expectException(HttpException::class);
        $this->controller->generateBaseVouchers($season, app(SeasonVoucherService::class));
    }

    private function createBasePackageProducts(): void
    {
        $category = Category::factory()->create(['is_restricted' => false]);
        Product::factory()->create(['name' => 'Tir du jeudi', 'category_id' => $category->id]);
        Product::factory()->create(['name' => 'Ouverture', 'category_id' => $category->id]);
        Product::factory()->create(['name' => 'Clôture', 'category_id' => $category->id]);
    }
}
