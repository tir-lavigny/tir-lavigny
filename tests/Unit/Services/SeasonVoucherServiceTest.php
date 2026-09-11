<?php

namespace Tests\Unit\Services;

use App\Models\Category;
use App\Models\Member;
use App\Models\Product;
use App\Models\Season;
use App\Models\Voucher;
use App\Services\SeasonVoucherService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

class SeasonVoucherServiceTest extends TestCase
{
    use RefreshDatabase;

    private SeasonVoucherService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new SeasonVoucherService;
    }

    // ── getBasePackage ──────────────────────────────────────────────────────

    public function test_get_base_package_returns_expected_products(): void
    {
        $package = $this->service->getBasePackage();

        $this->assertArrayHasKey('Tir du jeudi', $package);
        $this->assertArrayHasKey('Ouverture', $package);
        $this->assertArrayHasKey('Clôture', $package);
        $this->assertEquals(3, $package['Tir du jeudi']);
        $this->assertEquals(1, $package['Ouverture']);
        $this->assertEquals(1, $package['Clôture']);
    }

    // ── resolveProducts ─────────────────────────────────────────────────────

    public function test_resolve_products_returns_matching_models(): void
    {
        $category = Category::factory()->create(['is_restricted' => false]);
        Product::factory()->create(['name' => 'Tir du jeudi', 'category_id' => $category->id]);
        Product::factory()->create(['name' => 'Ouverture', 'category_id' => $category->id]);
        Product::factory()->create(['name' => 'Clôture', 'category_id' => $category->id]);

        $products = $this->service->resolveProducts($this->service->getBasePackage());

        $this->assertCount(3, $products);
        $this->assertArrayHasKey('Tir du jeudi', $products);
        $this->assertArrayHasKey('Ouverture', $products);
        $this->assertArrayHasKey('Clôture', $products);
    }

    public function test_resolve_products_is_case_insensitive(): void
    {
        $category = Category::factory()->create(['is_restricted' => false]);
        Product::factory()->create(['name' => 'tir du jeudi', 'category_id' => $category->id]);
        Product::factory()->create(['name' => 'OUVERTURE', 'category_id' => $category->id]);
        Product::factory()->create(['name' => 'clôture', 'category_id' => $category->id]);

        $products = $this->service->resolveProducts($this->service->getBasePackage());

        $this->assertCount(3, $products);
    }

    public function test_resolve_products_throws_when_product_missing(): void
    {
        $this->expectException(HttpException::class);

        $this->service->resolveProducts($this->service->getBasePackage());
    }

    // ── computePlan ─────────────────────────────────────────────────────────

    public function test_compute_plan_returns_empty_for_no_members(): void
    {
        $season = Season::factory()->create();
        $products = $this->createBasePackageProducts();

        $plan = $this->service->computePlan($season, $this->service->getBasePackage(), $products);

        $this->assertEmpty($plan);
    }

    public function test_compute_plan_includes_all_members(): void
    {
        $season = Season::factory()->create();
        $products = $this->createBasePackageProducts();
        Member::factory()->count(3)->create();

        $plan = $this->service->computePlan($season, $this->service->getBasePackage(), $products);

        $this->assertCount(3, $plan);
    }

    public function test_compute_plan_excludes_members_with_all_vouchers(): void
    {
        $season = Season::factory()->create();
        $products = $this->createBasePackageProducts();
        $member = Member::factory()->create();

        Voucher::factory()->percent()->count(3)->create([
            'member_id' => $member->id,
            'season_id' => $season->id,
            'product_id' => $products['Tir du jeudi']->id,
        ]);
        Voucher::factory()->percent()->create([
            'member_id' => $member->id,
            'season_id' => $season->id,
            'product_id' => $products['Ouverture']->id,
        ]);
        Voucher::factory()->percent()->create([
            'member_id' => $member->id,
            'season_id' => $season->id,
            'product_id' => $products['Clôture']->id,
        ]);

        $plan = $this->service->computePlan($season, $this->service->getBasePackage(), $products);

        $this->assertEmpty($plan);
    }

    public function test_compute_plan_calculates_missing_count(): void
    {
        $season = Season::factory()->create();
        $products = $this->createBasePackageProducts();
        $member = Member::factory()->create();

        Voucher::factory()->percent()->create([
            'member_id' => $member->id,
            'season_id' => $season->id,
            'product_id' => $products['Tir du jeudi']->id,
        ]);

        $plan = $this->service->computePlan($season, $this->service->getBasePackage(), $products);

        $this->assertCount(1, $plan);
        $this->assertEquals($member->id, $plan[0]['member_id']);
        $this->assertEquals([
            ['product_name' => 'Tir du jeudi', 'count' => 2],
            ['product_name' => 'Ouverture', 'count' => 1],
            ['product_name' => 'Clôture', 'count' => 1],
        ], $plan[0]['vouchers']);
    }

    public function test_compute_plan_includes_soft_deleted_vouchers_in_count(): void
    {
        $season = Season::factory()->create();
        $products = $this->createBasePackageProducts();
        $member = Member::factory()->create();

        $voucher = Voucher::factory()->percent()->create([
            'member_id' => $member->id,
            'season_id' => $season->id,
            'product_id' => $products['Ouverture']->id,
        ]);
        $voucher->delete();

        $plan = $this->service->computePlan($season, $this->service->getBasePackage(), $products);

        $this->assertEquals($member->id, $plan[0]['member_id']);
        $this->assertEquals([
            ['product_name' => 'Tir du jeudi', 'count' => 3],
            ['product_name' => 'Clôture', 'count' => 1],
        ], $plan[0]['vouchers']);
    }

    // ── generateBaseVouchers ────────────────────────────────────────────────

    public function test_generate_creates_vouchers_for_all_members(): void
    {
        $season = Season::factory()->create();
        $this->createBasePackageProducts();
        Member::factory()->count(3)->create();

        $this->service->generateBaseVouchers($season, $this->service->getBasePackage());

        $this->assertDatabaseCount('vouchers', 15);
    }

    public function test_generate_skips_members_with_all_vouchers(): void
    {
        $season = Season::factory()->create();
        $products = $this->createBasePackageProducts();
        $member = Member::factory()->create();

        foreach ($products as $productName => $product) {
            $count = match ($productName) {
                'Tir du jeudi' => 3,
                'Ouverture' => 1,
                'Clôture' => 1,
            };
            Voucher::factory()->percent()->count($count)->create([
                'member_id' => $member->id,
                'season_id' => $season->id,
                'product_id' => $product->id,
            ]);
        }

        $this->service->generateBaseVouchers($season, $this->service->getBasePackage());

        $this->assertDatabaseCount('vouchers', 5);
    }

    public function test_generate_creates_only_missing_vouchers(): void
    {
        $season = Season::factory()->create();
        $products = $this->createBasePackageProducts();
        $member = Member::factory()->create();

        Voucher::factory()->percent()->create([
            'member_id' => $member->id,
            'season_id' => $season->id,
            'product_id' => $products['Ouverture']->id,
        ]);

        $this->service->generateBaseVouchers($season, $this->service->getBasePackage());

        $this->assertDatabaseCount('vouchers', 5);
    }

    public function test_generate_sets_correct_discount_percent(): void
    {
        $season = Season::factory()->create();
        $this->createBasePackageProducts();
        Member::factory()->create();

        $this->service->generateBaseVouchers($season, $this->service->getBasePackage());

        $this->assertDatabaseHas('vouchers', [
            'discount_percent' => 100.00,
            'discount_fixed' => null,
        ]);
    }

    public function test_generate_sets_correct_foreign_keys(): void
    {
        $season = Season::factory()->create();
        $products = $this->createBasePackageProducts();
        $member = Member::factory()->create();

        $this->service->generateBaseVouchers($season, $this->service->getBasePackage());

        foreach ($products as $productName => $product) {
            $count = match ($productName) {
                'Tir du jeudi' => 3,
                'Ouverture' => 1,
                'Clôture' => 1,
            };
            $this->assertEquals($count, Voucher::where([
                'product_id' => $product->id,
                'season_id' => $season->id,
                'member_id' => $member->id,
            ])->count());
        }
    }

    public function test_generate_handles_missing_product(): void
    {
        $season = Season::factory()->create();

        $this->expectException(HttpException::class);
        $this->service->generateBaseVouchers($season, $this->service->getBasePackage());
    }

    public function test_generate_no_members_returns_no_vouchers(): void
    {
        $season = Season::factory()->create();
        $this->createBasePackageProducts();

        $this->service->generateBaseVouchers($season, $this->service->getBasePackage());

        $this->assertDatabaseCount('vouchers', 0);
    }

    private function createBasePackageProducts(): array
    {
        $category = Category::factory()->create(['is_restricted' => false]);

        return [
            'Tir du jeudi' => Product::factory()->create(['name' => 'Tir du jeudi', 'category_id' => $category->id]),
            'Ouverture' => Product::factory()->create(['name' => 'Ouverture', 'category_id' => $category->id]),
            'Clôture' => Product::factory()->create(['name' => 'Clôture', 'category_id' => $category->id]),
        ];
    }
}
