<?php

namespace Tests\Unit\Rules\Register;

use App\Rules\Register\RequiredIfProductAllowsCustomPrice;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class RequiredIfProductAllowsCustomPriceTest extends TestCase
{
    use RefreshDatabase;

    private RequiredIfProductAllowsCustomPrice $rule;

    protected function setUp(): void
    {
        parent::setUp();
        $this->rule = new RequiredIfProductAllowsCustomPrice;
    }

    private function createCategory(): int
    {
        return DB::table('categories')->insertGetId([
            'name' => 'Test Category',
            'is_restricted' => false,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function createProduct(bool $allowsCustom = true): int
    {
        return DB::table('products')->insertGetId([
            'name' => 'Test Product',
            'unit_price' => 10.00,
            'allows_custom_price' => $allowsCustom,
            'category_id' => $this->createCategory(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function test_passes_when_product_allows_custom_price_and_value_provided(): void
    {
        $productId = $this->createProduct(allowsCustom: true);

        request()->merge(['items' => [['product_id' => $productId]]]);

        $fail = fn ($msg) => $this->fail('Validation should pass but got: '.$msg);
        $this->rule->validate('items.0.custom_unit_price', 15.00, $fail);
        $this->assertTrue(true);
    }

    public function test_fails_when_product_allows_custom_price_and_value_null(): void
    {
        $productId = $this->createProduct(allowsCustom: true);

        request()->merge(['items' => [['product_id' => $productId]]]);

        $failed = false;
        $fail = function ($msg) use (&$failed) {
            $failed = true;
            $this->assertStringContainsString('required', $msg);
        };

        $this->rule->validate('items.0.custom_unit_price', null, $fail);
        $this->assertTrue($failed, 'Validation should fail when custom price is required but null');
    }

    public function test_fails_when_product_allows_custom_price_and_value_empty_string(): void
    {
        $productId = $this->createProduct(allowsCustom: true);

        request()->merge(['items' => [['product_id' => $productId]]]);

        $failed = false;
        $fail = function ($msg) use (&$failed) {
            $failed = true;
        };

        $this->rule->validate('items.0.custom_unit_price', '', $fail);
        $this->assertTrue($failed, 'Validation should fail when custom price is required but empty');
    }

    public function test_passes_when_product_does_not_allow_custom_price(): void
    {
        $productId = $this->createProduct(allowsCustom: false);

        request()->merge(['items' => [['product_id' => $productId]]]);

        $fail = fn ($msg) => $this->fail('Validation should pass but got: '.$msg);
        $this->rule->validate('items.0.custom_unit_price', null, $fail);
        $this->assertTrue(true);
    }

    public function test_skips_when_no_product_id(): void
    {
        request()->merge(['items' => [[]]]);

        $fail = fn ($msg) => $this->fail('Validation should be skipped: '.$msg);
        $this->rule->validate('items.0.custom_unit_price', null, $fail);
        $this->assertTrue(true);
    }
}
