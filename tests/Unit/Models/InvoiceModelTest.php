<?php

namespace Tests\Unit\Models;

use App\Domains\Invoicing\Enums\InvoiceItemType;
use App\Domains\Invoicing\Models\Invoice;
use App\Domains\Invoicing\Models\InvoiceItem;
use App\Domains\Invoicing\Models\InvoicePayment;
use App\Domains\Invoicing\Models\Payment;
use App\Models\Product;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class InvoiceModelTest extends TestCase
{
    use RefreshDatabase;

    public function test_exposes_money_columns_as_decimal_attributes(): void
    {
        $invoice = Invoice::factory()->create([
            'subtotal_cents' => 15000,
            'total_cents' => 12000,
        ]);

        $this->assertSame(15000, $invoice->subtotal_cents);
        $this->assertSame('150.00', $invoice->subtotal);
        $this->assertSame(12000, $invoice->total_cents);
        $this->assertSame('120.00', $invoice->total);
    }

    public function test_items_are_ordered_by_position(): void
    {
        $invoice = Invoice::factory()->create();
        $second = InvoiceItem::factory()->create(['invoice_id' => $invoice->id, 'position' => 2]);
        $first = InvoiceItem::factory()->create(['invoice_id' => $invoice->id, 'position' => 1]);

        $this->assertSame([$first->id, $second->id], $invoice->items()->pluck('id')->all());
    }

    public function test_discount_items_keep_negative_amounts(): void
    {
        $item = InvoiceItem::factory()->create([
            'type' => InvoiceItemType::Discount,
            'unit_price_cents' => -3000,
            'total_cents' => -3000,
        ]);

        $this->assertSame(-3000, $item->unit_price_cents);
        $this->assertSame('-30.00', $item->unit_price);
        $this->assertSame('-30.00', $item->total);
    }

    public function test_item_keeps_reference_to_soft_deleted_product(): void
    {
        $product = Product::factory()->create();
        $item = InvoiceItem::factory()->create(['product_id' => $product->id]);

        $product->delete();

        $this->assertTrue($item->fresh()->product->is($product));
    }

    public function test_payment_allocations_are_available_through_invoice(): void
    {
        $invoice = Invoice::factory()->create();
        $payment = Payment::factory()->create(['amount_cents' => 5000]);
        $allocation = InvoicePayment::factory()->create([
            'invoice_id' => $invoice->id,
            'payment_id' => $payment->id,
            'amount_cents' => 5000,
        ]);

        $this->assertTrue($invoice->paymentAllocations->first()->is($allocation));
        $this->assertTrue($invoice->payments->first()->is($payment));
        $this->assertSame(5000, (int) $invoice->payments->first()->pivot->amount_cents);
    }
}
