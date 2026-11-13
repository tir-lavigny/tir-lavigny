<?php

namespace Tests\Unit\Http\Controllers\API\Register;

use App\Http\Controllers\API\Register\MemberController;
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

class MemberControllerTest extends TestCase
{
    use RefreshDatabase;

    private MemberController $controller;

    protected function setUp(): void
    {
        parent::setUp();
        $this->controller = new MemberController;
    }

    public function test_vouchers_returns_unused_vouchers_for_member_in_current_season(): void
    {
        $season = Season::factory()->create([
            'start_date' => Carbon::now()->startOfYear(),
            'end_date' => Carbon::now()->endOfYear(),
        ]);
        $day = OperatingDay::factory()->create([
            'date' => Carbon::today('Europe/Zurich'),
            'season_id' => $season->id,
            'closed_at' => null,
        ]);
        $category = Category::factory()->create(['is_restricted' => false]);
        $product = Product::factory()->create(['category_id' => $category->id]);
        $member = Member::factory()->create();

        $voucher = Voucher::factory()->percent()->create([
            'product_id' => $product->id,
            'season_id' => $season->id,
            'member_id' => $member->id,
        ]);

        $response = $this->controller->vouchers($member);
        $data = json_decode($response->getContent(), true);

        $this->assertEquals(200, $response->status());
        $this->assertCount(1, $data['data']);
        $this->assertEquals($voucher->id, $data['data'][0]['id']);
    }

    public function test_vouchers_excludes_used_vouchers(): void
    {
        $season = Season::factory()->create([
            'start_date' => Carbon::now()->startOfYear(),
            'end_date' => Carbon::now()->endOfYear(),
        ]);
        $day = OperatingDay::factory()->create([
            'date' => Carbon::today('Europe/Zurich'),
            'season_id' => $season->id,
            'closed_at' => null,
        ]);
        $category = Category::factory()->create(['is_restricted' => false]);
        $product = Product::factory()->create(['category_id' => $category->id]);
        $member = Member::factory()->create();

        $usedVoucher = Voucher::factory()->percent()->create([
            'product_id' => $product->id,
            'season_id' => $season->id,
            'member_id' => $member->id,
        ]);
        $ticket = Ticket::factory()->cash()->create(['operating_day_id' => $day->id]);
        TicketItem::factory()->create([
            'ticket_id' => $ticket->id,
            'product_id' => $product->id,
            'voucher_id' => $usedVoucher->id,
        ]);

        $unusedVoucher = Voucher::factory()->percent()->create([
            'product_id' => $product->id,
            'season_id' => $season->id,
            'member_id' => $member->id,
        ]);

        $response = $this->controller->vouchers($member);
        $data = json_decode($response->getContent(), true);

        $this->assertEquals(200, $response->status());
        $this->assertCount(1, $data['data']);
        $this->assertEquals($unusedVoucher->id, $data['data'][0]['id']);
    }

    public function test_vouchers_excludes_vouchers_from_other_seasons(): void
    {
        $currentSeason = Season::factory()->create([
            'start_date' => Carbon::now()->startOfYear(),
            'end_date' => Carbon::now()->endOfYear(),
        ]);
        $otherSeason = Season::factory()->create([
            'start_date' => Carbon::now()->addYear()->startOfYear(),
            'end_date' => Carbon::now()->addYear()->endOfYear(),
        ]);
        OperatingDay::factory()->create([
            'date' => Carbon::today('Europe/Zurich'),
            'season_id' => $currentSeason->id,
            'closed_at' => null,
        ]);
        $category = Category::factory()->create(['is_restricted' => false]);
        $product = Product::factory()->create(['category_id' => $category->id]);
        $member = Member::factory()->create();

        Voucher::factory()->percent()->create([
            'product_id' => $product->id,
            'season_id' => $otherSeason->id,
            'member_id' => $member->id,
        ]);

        $response = $this->controller->vouchers($member);
        $data = json_decode($response->getContent(), true);

        $this->assertEquals(200, $response->status());
        $this->assertCount(0, $data['data']);
    }

    public function test_vouchers_returns_404_when_no_open_day(): void
    {
        $member = Member::factory()->create();

        $response = $this->controller->vouchers($member);

        $this->assertEquals(404, $response->status());
    }

    public function test_vouchers_excludes_vouchers_for_other_members(): void
    {
        $season = Season::factory()->create([
            'start_date' => Carbon::now()->startOfYear(),
            'end_date' => Carbon::now()->endOfYear(),
        ]);
        OperatingDay::factory()->create([
            'date' => Carbon::today('Europe/Zurich'),
            'season_id' => $season->id,
            'closed_at' => null,
        ]);
        $category = Category::factory()->create(['is_restricted' => false]);
        $product = Product::factory()->create(['category_id' => $category->id]);
        $member = Member::factory()->create();
        $otherMember = Member::factory()->create();

        Voucher::factory()->percent()->create([
            'product_id' => $product->id,
            'season_id' => $season->id,
            'member_id' => $otherMember->id,
        ]);

        $response = $this->controller->vouchers($member);
        $data = json_decode($response->getContent(), true);

        $this->assertEquals(200, $response->status());
        $this->assertCount(0, $data['data']);
    }

    public function test_vouchers_eager_loads_product_relation(): void
    {
        $season = Season::factory()->create([
            'start_date' => Carbon::now()->startOfYear(),
            'end_date' => Carbon::now()->endOfYear(),
        ]);
        OperatingDay::factory()->create([
            'date' => Carbon::today('Europe/Zurich'),
            'season_id' => $season->id,
            'closed_at' => null,
        ]);
        $category = Category::factory()->create(['is_restricted' => false]);
        $product = Product::factory()->create(['category_id' => $category->id]);
        $member = Member::factory()->create();

        Voucher::factory()->percent()->create([
            'product_id' => $product->id,
            'season_id' => $season->id,
            'member_id' => $member->id,
        ]);

        $response = $this->controller->vouchers($member);
        $data = json_decode($response->getContent(), true);

        $this->assertEquals(200, $response->status());
        $this->assertArrayHasKey('product', $data['data'][0]);
        $this->assertEquals($product->id, $data['data'][0]['product']['id']);
    }

    public function test_vouchers_rejects_passive_member(): void
    {
        $member = Member::factory()->passive()->create();

        $response = $this->controller->vouchers($member);

        $this->assertEquals(404, $response->status());
    }
}
