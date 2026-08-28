<?php

namespace Tests\Unit\Models;

use App\Models\Product;
use App\Models\Ticket;
use App\Models\TicketItem;
use App\Models\Voucher;
use Illuminate\Foundation\Testing\RefreshDatabase;
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
}
