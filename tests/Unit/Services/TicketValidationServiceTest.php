<?php

namespace Tests\Unit\Services;

use App\Models\Category;
use App\Models\Member;
use App\Models\OperatingDay;
use App\Models\Product;
use App\Models\Season;
use App\Models\Ticket;
use App\Models\TicketItem;
use App\Models\Voucher;
use App\Services\TicketValidationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TicketValidationServiceTest extends TestCase
{
    use RefreshDatabase;

    private TicketValidationService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new TicketValidationService;
    }

    private function makeValidator(): \Illuminate\Validation\Validator
    {
        return \Validator::make([], []);
    }

    private function getErrorMessages(\Illuminate\Validation\Validator $validator, string $key): array
    {
        return $validator->errors()->get($key);
    }

    private function hasError(\Illuminate\Validation\Validator $validator, string $key): bool
    {
        return $validator->errors()->has($key);
    }

    // ── Custom price validation ─────────────────────────────────────────────

    public function test_requires_custom_price_when_product_allows_custom(): void
    {
        $category = Category::factory()->create(['is_restricted' => false]);
        $product = Product::factory()->create([
            'category_id' => $category->id,
            'allows_custom_price' => true,
        ]);

        $validator = $this->makeValidator();
        $this->service->validateItems(
            [['product_id' => $product->id, 'quantity' => 1]],
            1,
            null,
            $validator
        );

        $this->assertTrue($this->hasError($validator, 'items.0.custom_unit_price'));
    }

    public function test_accepts_custom_price_when_product_allows_custom(): void
    {
        $category = Category::factory()->create(['is_restricted' => false]);
        $product = Product::factory()->create([
            'category_id' => $category->id,
            'allows_custom_price' => true,
        ]);

        $validator = $this->makeValidator();
        $this->service->validateItems(
            [['product_id' => $product->id, 'quantity' => 1, 'custom_unit_price' => 15.00]],
            1,
            null,
            $validator
        );

        $this->assertFalse($this->hasError($validator, 'items.0.custom_unit_price'));
    }

    public function test_does_not_require_custom_price_when_product_does_not_allow_custom(): void
    {
        $category = Category::factory()->create(['is_restricted' => false]);
        $product = Product::factory()->create([
            'category_id' => $category->id,
            'allows_custom_price' => false,
        ]);

        $validator = $this->makeValidator();
        $this->service->validateItems(
            [['product_id' => $product->id, 'quantity' => 1]],
            1,
            null,
            $validator
        );

        $this->assertFalse($this->hasError($validator, 'items.0.custom_unit_price'));
    }

    public function test_accepts_empty_string_custom_price_when_product_does_not_allow_custom(): void
    {
        $category = Category::factory()->create(['is_restricted' => false]);
        $product = Product::factory()->create([
            'category_id' => $category->id,
            'allows_custom_price' => false,
        ]);

        $validator = $this->makeValidator();
        $this->service->validateItems(
            [['product_id' => $product->id, 'quantity' => 1, 'custom_unit_price' => '']],
            1,
            null,
            $validator
        );

        $this->assertFalse($this->hasError($validator, 'items.0.custom_unit_price'));
    }

    // ── Voucher uniqueness ──────────────────────────────────────────────────

    public function test_rejects_duplicate_voucher_id_in_same_ticket(): void
    {
        $season = Season::factory()->create();
        $category = Category::factory()->create(['is_restricted' => false]);
        $product = Product::factory()->create(['category_id' => $category->id]);
        $day = OperatingDay::factory()->create(['season_id' => $season->id]);
        $voucher = Voucher::factory()->percent()->create([
            'product_id' => $product->id,
            'season_id' => $season->id,
        ]);

        $validator = $this->makeValidator();
        $this->service->validateItems(
            [
                ['product_id' => $product->id, 'quantity' => 1, 'voucher_ids' => [$voucher->id]],
                ['product_id' => $product->id, 'quantity' => 1, 'voucher_ids' => [$voucher->id]],
            ],
            $day->id,
            null,
            $validator
        );

        $this->assertTrue($this->hasError($validator, 'items.1.voucher_ids'));
        $this->assertContains(
            'Each voucher can only be used once per ticket.',
            $this->getErrorMessages($validator, 'items.1.voucher_ids')
        );
    }

    public function test_allows_different_voucher_ids(): void
    {
        $season = Season::factory()->create();
        $category = Category::factory()->create(['is_restricted' => false]);
        $product = Product::factory()->create(['category_id' => $category->id]);
        $day = OperatingDay::factory()->create(['season_id' => $season->id]);
        $voucher1 = Voucher::factory()->percent()->create([
            'product_id' => $product->id,
            'season_id' => $season->id,
        ]);
        $voucher2 = Voucher::factory()->percent()->create([
            'product_id' => $product->id,
            'season_id' => $season->id,
        ]);

        $validator = $this->makeValidator();
        $this->service->validateItems(
            [
                ['product_id' => $product->id, 'quantity' => 1, 'voucher_ids' => [$voucher1->id]],
                ['product_id' => $product->id, 'quantity' => 1, 'voucher_ids' => [$voucher2->id]],
            ],
            $day->id,
            null,
            $validator
        );

        $this->assertFalse($this->hasError($validator, 'items.0.voucher_ids.0'));
        $this->assertFalse($this->hasError($validator, 'items.1.voucher_ids'));
    }

    public function test_rejects_more_vouchers_than_quantity(): void
    {
        $season = Season::factory()->create();
        $category = Category::factory()->create(['is_restricted' => false]);
        $product = Product::factory()->create(['category_id' => $category->id]);
        $day = OperatingDay::factory()->create(['season_id' => $season->id]);
        $voucher1 = Voucher::factory()->percent()->create([
            'product_id' => $product->id,
            'season_id' => $season->id,
        ]);
        $voucher2 = Voucher::factory()->percent()->create([
            'product_id' => $product->id,
            'season_id' => $season->id,
        ]);

        $validator = $this->makeValidator();
        $this->service->validateItems(
            [['product_id' => $product->id, 'quantity' => 1, 'voucher_ids' => [$voucher1->id, $voucher2->id]]],
            $day->id,
            null,
            $validator
        );

        $this->assertTrue($this->hasError($validator, 'items.0.voucher_ids'));
        $this->assertContains(
            'You cannot apply more vouchers than the quantity of the product.',
            $this->getErrorMessages($validator, 'items.0.voucher_ids')
        );
    }

    public function test_rejects_duplicate_voucher_id_within_same_item(): void
    {
        $season = Season::factory()->create();
        $category = Category::factory()->create(['is_restricted' => false]);
        $product = Product::factory()->create(['category_id' => $category->id]);
        $day = OperatingDay::factory()->create(['season_id' => $season->id]);
        $voucher = Voucher::factory()->percent()->create([
            'product_id' => $product->id,
            'season_id' => $season->id,
        ]);

        $validator = $this->makeValidator();
        $this->service->validateItems(
            [['product_id' => $product->id, 'quantity' => 2, 'voucher_ids' => [$voucher->id, $voucher->id]]],
            $day->id,
            null,
            $validator
        );

        $this->assertTrue($this->hasError($validator, 'items.0.voucher_ids'));
        $this->assertContains(
            'Each voucher can only be used once per ticket.',
            $this->getErrorMessages($validator, 'items.0.voucher_ids')
        );
    }

    // ── Voucher product match ───────────────────────────────────────────────

    public function test_rejects_voucher_for_wrong_product(): void
    {
        $season = Season::factory()->create();
        $category = Category::factory()->create(['is_restricted' => false]);
        $product1 = Product::factory()->create(['category_id' => $category->id]);
        $product2 = Product::factory()->create(['category_id' => $category->id]);
        $day = OperatingDay::factory()->create(['season_id' => $season->id]);
        $voucher = Voucher::factory()->percent()->create([
            'product_id' => $product2->id,
            'season_id' => $season->id,
        ]);

        $validator = $this->makeValidator();
        $this->service->validateItems(
            [['product_id' => $product1->id, 'quantity' => 1, 'voucher_ids' => [$voucher->id]]],
            $day->id,
            null,
            $validator
        );

        $this->assertTrue($this->hasError($validator, 'items.0.voucher_ids.0'));
        $this->assertContains(
            'The voucher does not match the selected product.',
            $this->getErrorMessages($validator, 'items.0.voucher_ids.0')
        );
    }

    public function test_allows_voucher_for_correct_product(): void
    {
        $season = Season::factory()->create();
        $category = Category::factory()->create(['is_restricted' => false]);
        $product = Product::factory()->create(['category_id' => $category->id]);
        $day = OperatingDay::factory()->create(['season_id' => $season->id]);
        $voucher = Voucher::factory()->percent()->create([
            'product_id' => $product->id,
            'season_id' => $season->id,
        ]);

        $validator = $this->makeValidator();
        $this->service->validateItems(
            [['product_id' => $product->id, 'quantity' => 1, 'voucher_ids' => [$voucher->id]]],
            $day->id,
            null,
            $validator
        );

        $this->assertFalse($this->hasError($validator, 'items.0.voucher_ids.0'));
    }

    // ── Voucher season match ────────────────────────────────────────────────

    public function test_rejects_voucher_for_wrong_season(): void
    {
        $season1 = Season::factory()->create();
        $season2 = Season::factory()->create();
        $category = Category::factory()->create(['is_restricted' => false]);
        $product = Product::factory()->create(['category_id' => $category->id]);
        $day = OperatingDay::factory()->create(['season_id' => $season1->id]);
        $voucher = Voucher::factory()->percent()->create([
            'product_id' => $product->id,
            'season_id' => $season2->id,
        ]);

        $validator = $this->makeValidator();
        $this->service->validateItems(
            [['product_id' => $product->id, 'quantity' => 1, 'voucher_ids' => [$voucher->id]]],
            $day->id,
            null,
            $validator
        );

        $this->assertTrue($this->hasError($validator, 'items.0.voucher_ids.0'));
        $this->assertContains(
            'The voucher is not valid for the current season.',
            $this->getErrorMessages($validator, 'items.0.voucher_ids.0')
        );
    }

    public function test_allows_voucher_for_correct_season(): void
    {
        $season = Season::factory()->create();
        $category = Category::factory()->create(['is_restricted' => false]);
        $product = Product::factory()->create(['category_id' => $category->id]);
        $day = OperatingDay::factory()->create(['season_id' => $season->id]);
        $voucher = Voucher::factory()->percent()->create([
            'product_id' => $product->id,
            'season_id' => $season->id,
        ]);

        $validator = $this->makeValidator();
        $this->service->validateItems(
            [['product_id' => $product->id, 'quantity' => 1, 'voucher_ids' => [$voucher->id]]],
            $day->id,
            null,
            $validator
        );

        $this->assertFalse($this->hasError($validator, 'items.0.voucher_ids.0'));
    }

    // ── Voucher usage ───────────────────────────────────────────────────────

    public function test_rejects_already_used_voucher(): void
    {
        $season = Season::factory()->create();
        $category = Category::factory()->create(['is_restricted' => false]);
        $product = Product::factory()->create(['category_id' => $category->id]);
        $day = OperatingDay::factory()->create(['season_id' => $season->id]);
        $voucher = Voucher::factory()->percent()->create([
            'product_id' => $product->id,
            'season_id' => $season->id,
        ]);
        $ticket = Ticket::factory()->cash()->create(['operating_day_id' => $day->id]);
        TicketItem::factory()->create([
            'ticket_id' => $ticket->id,
            'product_id' => $product->id,
            'voucher_id' => $voucher->id,
        ]);

        $validator = $this->makeValidator();
        $this->service->validateItems(
            [['product_id' => $product->id, 'quantity' => 1, 'voucher_ids' => [$voucher->id]]],
            $day->id,
            null,
            $validator
        );

        $this->assertTrue($this->hasError($validator, 'items.0.voucher_ids.0'));
        $this->assertContains(
            'The voucher has already been used.',
            $this->getErrorMessages($validator, 'items.0.voucher_ids.0')
        );
    }

    public function test_allows_unused_voucher(): void
    {
        $season = Season::factory()->create();
        $category = Category::factory()->create(['is_restricted' => false]);
        $product = Product::factory()->create(['category_id' => $category->id]);
        $day = OperatingDay::factory()->create(['season_id' => $season->id]);
        $voucher = Voucher::factory()->percent()->create([
            'product_id' => $product->id,
            'season_id' => $season->id,
        ]);

        $validator = $this->makeValidator();
        $this->service->validateItems(
            [['product_id' => $product->id, 'quantity' => 1, 'voucher_ids' => [$voucher->id]]],
            $day->id,
            null,
            $validator
        );

        $this->assertFalse($this->hasError($validator, 'items.0.voucher_ids.0'));
    }

    // ── Voucher membership ──────────────────────────────────────────────────

    public function test_rejects_voucher_belonging_to_different_member(): void
    {
        $season = Season::factory()->create();
        $category = Category::factory()->create(['is_restricted' => false]);
        $product = Product::factory()->create(['category_id' => $category->id]);
        $day = OperatingDay::factory()->create(['season_id' => $season->id]);
        $member1 = Member::factory()->create();
        $member2 = Member::factory()->create();
        $voucher = Voucher::factory()->percent()->create([
            'product_id' => $product->id,
            'season_id' => $season->id,
            'member_id' => $member2->id,
        ]);

        $validator = $this->makeValidator();
        $this->service->validateItems(
            [['product_id' => $product->id, 'quantity' => 1, 'voucher_ids' => [$voucher->id]]],
            $day->id,
            $member1->id,
            $validator
        );

        $this->assertTrue($this->hasError($validator, 'items.0.voucher_ids.0'));
        $this->assertContains(
            'The voucher does not belong to this member.',
            $this->getErrorMessages($validator, 'items.0.voucher_ids.0')
        );
    }

    public function test_allows_voucher_belonging_to_same_member(): void
    {
        $season = Season::factory()->create();
        $category = Category::factory()->create(['is_restricted' => false]);
        $product = Product::factory()->create(['category_id' => $category->id]);
        $day = OperatingDay::factory()->create(['season_id' => $season->id]);
        $member = Member::factory()->create();
        $voucher = Voucher::factory()->percent()->create([
            'product_id' => $product->id,
            'season_id' => $season->id,
            'member_id' => $member->id,
        ]);

        $validator = $this->makeValidator();
        $this->service->validateItems(
            [['product_id' => $product->id, 'quantity' => 1, 'voucher_ids' => [$voucher->id]]],
            $day->id,
            $member->id,
            $validator
        );

        $this->assertFalse($this->hasError($validator, 'items.0.voucher_ids.0'));
    }

    public function test_skips_member_check_when_no_member_id(): void
    {
        $season = Season::factory()->create();
        $category = Category::factory()->create(['is_restricted' => false]);
        $product = Product::factory()->create(['category_id' => $category->id]);
        $day = OperatingDay::factory()->create(['season_id' => $season->id]);
        $member = Member::factory()->create();
        $voucher = Voucher::factory()->percent()->create([
            'product_id' => $product->id,
            'season_id' => $season->id,
            'member_id' => $member->id,
        ]);

        $validator = $this->makeValidator();
        $this->service->validateItems(
            [['product_id' => $product->id, 'quantity' => 1, 'voucher_ids' => [$voucher->id]]],
            $day->id,
            null,
            $validator
        );

        $this->assertFalse($this->hasError($validator, 'items.0.voucher_ids.0'));
    }

    // ── No operating day ────────────────────────────────────────────────────

    public function test_skips_voucher_rules_when_operating_day_not_found(): void
    {
        $category = Category::factory()->create(['is_restricted' => false]);
        $product = Product::factory()->create(['category_id' => $category->id]);
        $voucher = Voucher::factory()->percent()->create(['product_id' => $product->id]);

        $validator = $this->makeValidator();
        $this->service->validateItems(
            [['product_id' => $product->id, 'quantity' => 1, 'voucher_ids' => [$voucher->id]]],
            9999,
            null,
            $validator
        );

        $this->assertFalse($this->hasError($validator, 'items.0.voucher_ids.0'));
    }

    // ── No errors on clean input ────────────────────────────────────────────

    public function test_no_errors_on_clean_input(): void
    {
        $season = Season::factory()->create();
        $category = Category::factory()->create(['is_restricted' => false]);
        $product = Product::factory()->create(['category_id' => $category->id]);
        $day = OperatingDay::factory()->create(['season_id' => $season->id]);

        $validator = $this->makeValidator();
        $this->service->validateItems(
            [['product_id' => $product->id, 'quantity' => 2]],
            $day->id,
            null,
            $validator
        );

        $this->assertEmpty($validator->errors()->toArray());
    }
}
