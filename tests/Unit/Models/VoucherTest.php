<?php

namespace Tests\Unit\Models;

use App\Models\Product;
use App\Models\Ticket;
use App\Models\TicketItem;
use App\Models\Voucher;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

class VoucherTest extends TestCase
{
    use RefreshDatabase;

    public function test_is_used_returns_false_when_no_ticket_items(): void
    {
        $voucher = Voucher::factory()->create();

        $this->assertFalse($voucher->isUsed());
    }

    public function test_is_used_returns_true_when_ticket_items_exist(): void
    {
        $voucher = Voucher::factory()->create();
        TicketItem::factory()->withVoucher()->create([
            'voucher_id' => $voucher->id,
        ]);

        $this->assertTrue($voucher->isUsed());
    }

    public function test_is_used_returns_true_with_multiple_ticket_items(): void
    {
        $voucher = Voucher::factory()->create();
        TicketItem::factory()->count(3)->create([
            'voucher_id' => $voucher->id,
            'ticket_id' => Ticket::factory(),
        ]);

        $this->assertTrue($voucher->isUsed());
    }

    public function test_voucher_belongs_to_product(): void
    {
        $voucher = Voucher::factory()->create();

        $this->assertInstanceOf(Product::class, $voucher->product);
    }

    public function test_used_voucher_cannot_be_updated(): void
    {
        $voucher = Voucher::factory()->percent()->create([
            'discount_percent' => 10.00,
        ]);

        TicketItem::factory()->create([
            'voucher_id' => $voucher->id,
        ]);

        $voucher->discount_percent = 20.00;

        $this->expectException(HttpException::class);
        $voucher->save();
    }

    public function test_used_voucher_cannot_be_deleted(): void
    {
        $voucher = Voucher::factory()->percent()->create();

        TicketItem::factory()->create([
            'voucher_id' => $voucher->id,
        ]);

        $this->expectException(HttpException::class);
        $voucher->delete();
    }

    public function test_unused_voucher_can_be_updated(): void
    {
        $voucher = Voucher::factory()->percent()->create([
            'discount_percent' => 10.00,
        ]);

        $voucher->discount_percent = 20.00;
        $voucher->save();

        $this->assertDatabaseHas('vouchers', [
            'id' => $voucher->id,
            'discount_percent' => 20.00,
        ]);
    }

    public function test_unused_voucher_can_be_deleted(): void
    {
        $voucher = Voucher::factory()->percent()->create();

        $voucher->delete();

        $this->assertSoftDeleted('vouchers', ['id' => $voucher->id]);
    }

    public function test_used_voucher_update_does_not_change_database(): void
    {
        $voucher = Voucher::factory()->percent()->create([
            'discount_percent' => 10.00,
        ]);

        TicketItem::factory()->create([
            'voucher_id' => $voucher->id,
        ]);

        try {
            $voucher->discount_percent = 20.00;
            $voucher->save();
        } catch (\Exception $e) {
            // Expected
        }

        $this->assertDatabaseHas('vouchers', [
            'id' => $voucher->id,
            'discount_percent' => 10.00,
        ]);
    }
}
