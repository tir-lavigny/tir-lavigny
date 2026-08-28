<?php

namespace Tests\Unit\Rules\Register;

use App\Rules\Register\VoucherIsUsable;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class VoucherIsUsableTest extends TestCase
{
    use RefreshDatabase;

    private VoucherIsUsable $rule;

    protected function setUp(): void
    {
        parent::setUp();
        $this->rule = new VoucherIsUsable;
    }

    private int $categoryCounter = 0;

    private function createCategory(): int
    {
        $this->categoryCounter++;

        return DB::table('categories')->insertGetId([
            'name' => "Test Category {$this->categoryCounter}",
            'is_restricted' => false,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function createSeason(string $start, string $end): int
    {
        return DB::table('seasons')->insertGetId([
            'name' => 'Test Season',
            'start_date' => $start,
            'end_date' => $end,
            'initial_balance' => 0,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function createProduct(): int
    {
        return DB::table('products')->insertGetId([
            'name' => 'Test Product',
            'unit_price' => 10.00,
            'allows_custom_price' => false,
            'category_id' => $this->createCategory(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function createMember(): int
    {
        return DB::table('members')->insertGetId([
            'first_name' => 'John',
            'last_name' => 'Doe',
            'member_number' => uniqid(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function createOperatingDay(int $seasonId): int
    {
        return DB::table('operating_days')->insertGetId([
            'date' => Carbon::now()->toDateString(),
            'started_at' => Carbon::now(),
            'season_id' => $seasonId,
            'total_cash' => 0,
            'total_twint' => 0,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function test_passes_when_voucher_is_valid_and_usable(): void
    {
        $seasonId = $this->createSeason(
            Carbon::now()->startOfYear()->toDateString(),
            Carbon::now()->endOfYear()->toDateString(),
        );
        $productId = $this->createProduct();
        $memberId = $this->createMember();

        DB::table('vouchers')->insert([
            'code' => 'TESTCODE',
            'discount_percent' => 10,
            'product_id' => $productId,
            'season_id' => $seasonId,
            'member_id' => $memberId,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        request()->merge([
            'code' => 'TESTCODE',
            'product_id' => $productId,
            'season_id' => $seasonId,
        ]);

        $fail = fn ($msg) => $this->fail('Validation should pass but got: '.$msg);
        $this->rule->validate('code', 'TESTCODE', $fail);
        $this->assertTrue(true);
    }

    public function test_fails_when_voucher_code_does_not_exist(): void
    {
        request()->merge([
            'code' => 'NONEXISTENT',
            'product_id' => 1,
            'season_id' => 1,
        ]);

        $failed = false;
        $fail = function ($msg) use (&$failed) {
            $failed = true;
            $this->assertStringContainsString('invalid', $msg);
        };

        $this->rule->validate('code', 'NONEXISTENT', $fail);
        $this->assertTrue($failed, 'Validation should fail for nonexistent voucher');
    }

    public function test_fails_when_voucher_is_already_used(): void
    {
        $seasonId = $this->createSeason(
            Carbon::now()->startOfYear()->toDateString(),
            Carbon::now()->endOfYear()->toDateString(),
        );
        $productId = $this->createProduct();
        $memberId = $this->createMember();
        $operatingDayId = $this->createOperatingDay($seasonId);

        $voucherId = DB::table('vouchers')->insertGetId([
            'code' => 'USEDCODE',
            'discount_percent' => 10,
            'product_id' => $productId,
            'season_id' => $seasonId,
            'member_id' => $memberId,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $ticketId = DB::table('tickets')->insertGetId([
            'total' => 10.00,
            'payment_method' => 'cash',
            'operating_day_id' => $operatingDayId,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('ticket_items')->insert([
            'product_id' => $productId,
            'ticket_id' => $ticketId,
            'voucher_id' => $voucherId,
            'quantity' => 1,
            'unit_price' => 10.00,
            'subtotal' => 10.00,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        request()->merge([
            'code' => 'USEDCODE',
            'product_id' => $productId,
            'season_id' => $seasonId,
        ]);

        $failed = false;
        $fail = function ($msg) use (&$failed) {
            $failed = true;
            $this->assertStringContainsString('already been used', $msg);
        };

        $this->rule->validate('code', 'USEDCODE', $fail);
        $this->assertTrue($failed, 'Validation should fail for used voucher');
    }

    public function test_fails_when_voucher_belongs_to_different_product(): void
    {
        $seasonId = $this->createSeason(
            Carbon::now()->startOfYear()->toDateString(),
            Carbon::now()->endOfYear()->toDateString(),
        );
        $productId = $this->createProduct();
        $otherProductId = $this->createProduct();
        $memberId = $this->createMember();

        DB::table('vouchers')->insert([
            'code' => 'WRONGPROD',
            'discount_percent' => 10,
            'product_id' => $productId,
            'season_id' => $seasonId,
            'member_id' => $memberId,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        request()->merge([
            'code' => 'WRONGPROD',
            'product_id' => $otherProductId,
            'season_id' => $seasonId,
        ]);

        $failed = false;
        $fail = function ($msg) use (&$failed) {
            $failed = true;
            $this->assertStringContainsString('does not belong to this product', $msg);
        };

        $this->rule->validate('code', 'WRONGPROD', $fail);
        $this->assertTrue($failed, 'Validation should fail when product does not match');
    }

    public function test_fails_when_voucher_belongs_to_different_season(): void
    {
        $seasonId = $this->createSeason(
            Carbon::now()->startOfYear()->toDateString(),
            Carbon::now()->endOfYear()->toDateString(),
        );
        $otherSeasonId = $this->createSeason(
            Carbon::now()->addYear()->startOfYear()->toDateString(),
            Carbon::now()->addYear()->endOfYear()->toDateString(),
        );
        $productId = $this->createProduct();
        $memberId = $this->createMember();

        DB::table('vouchers')->insert([
            'code' => 'WRONGSEASON',
            'discount_percent' => 10,
            'product_id' => $productId,
            'season_id' => $seasonId,
            'member_id' => $memberId,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        request()->merge([
            'code' => 'WRONGSEASON',
            'product_id' => $productId,
            'season_id' => $otherSeasonId,
        ]);

        $failed = false;
        $fail = function ($msg) use (&$failed) {
            $failed = true;
            $this->assertStringContainsString('does not belong to this season', $msg);
        };

        $this->rule->validate('code', 'WRONGSEASON', $fail);
        $this->assertTrue($failed, 'Validation should fail when season does not match');
    }

    public function test_fails_when_season_is_not_active(): void
    {
        $seasonId = $this->createSeason(
            Carbon::now()->subYear()->startOfYear()->toDateString(),
            Carbon::now()->subYear()->endOfYear()->toDateString(),
        );
        $productId = $this->createProduct();
        $memberId = $this->createMember();

        DB::table('vouchers')->insert([
            'code' => 'EXPIRED',
            'discount_percent' => 10,
            'product_id' => $productId,
            'season_id' => $seasonId,
            'member_id' => $memberId,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        request()->merge([
            'code' => 'EXPIRED',
            'product_id' => $productId,
            'season_id' => $seasonId,
        ]);

        $failed = false;
        $fail = function ($msg) use (&$failed) {
            $failed = true;
            $this->assertStringContainsString('not currently active', $msg);
        };

        $this->rule->validate('code', 'EXPIRED', $fail);
        $this->assertTrue($failed, 'Validation should fail when season is expired');
    }

    public function test_skips_when_required_fields_are_missing(): void
    {
        request()->merge([
            'code' => null,
            'product_id' => null,
            'season_id' => null,
        ]);

        $fail = fn ($msg) => $this->fail('Validation should be skipped: '.$msg);
        $this->rule->validate('code', null, $fail);
        $this->assertTrue(true);
    }
}
