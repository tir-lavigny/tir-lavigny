<?php

namespace Tests\Unit\Actions\Invoices;

use App\Domains\Invoicing\Actions\Invoices\CancelInvoice;
use App\Domains\Invoicing\Actions\Invoices\CreateInvoice;
use App\Domains\Invoicing\Actions\Invoices\DeleteDraftInvoice;
use App\Domains\Invoicing\Actions\Invoices\IssueInvoice;
use App\Domains\Invoicing\Actions\Invoices\UpdateDraftInvoice;
use App\Domains\Invoicing\Enums\CustomerAccountEntryType;
use App\Domains\Invoicing\Enums\InvoiceStatus;
use App\Domains\Invoicing\Events\InvoiceCancelled;
use App\Domains\Invoicing\Events\InvoiceIssued;
use App\Domains\Invoicing\Exceptions\InvoiceNotCancellable;
use App\Domains\Invoicing\Exceptions\InvoiceNotDraft;
use App\Domains\Invoicing\Exceptions\InvoiceNotIssuable;
use App\Domains\Invoicing\Models\BillingCustomer;
use App\Domains\Invoicing\Models\InvoiceItem;
use App\Domains\Invoicing\Models\InvoicePayment;
use App\Domains\Invoicing\Models\Payment;
use App\Domains\Invoicing\Services\CustomerAccountLedger;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use LogicException;
use Tests\TestCase;

class InvoiceActionsTest extends TestCase
{
    use RefreshDatabase;

    public function test_creates_a_draft_invoice_with_lines_and_snapshot(): void
    {
        $actor = User::factory()->create();
        $customer = BillingCustomer::factory()->create([
            'first_name' => 'Alice',
            'last_name' => 'Martin',
            'address' => 'Rue du Lac 4',
            'postal_code' => '1003',
            'city' => 'Lausanne',
            'country' => 'CH',
            'email' => 'alice@example.test',
        ]);

        $invoice = app(CreateInvoice::class)->execute($this->invoiceData($customer->id), $actor);

        $this->assertSame(InvoiceStatus::Draft, $invoice->status);
        $this->assertNull($invoice->invoice_number);
        $this->assertSame($customer->id, $invoice->billing_customer_id);
        $this->assertSame('Alice Martin', $invoice->recipient_name);
        $this->assertSame('Rue du Lac 4', $invoice->recipient_address);
        $this->assertSame(15000, $invoice->subtotal_cents);
        $this->assertSame(12000, $invoice->total_cents);
        $this->assertSame($actor->id, $invoice->created_by);
        $this->assertCount(2, $invoice->items);
        $this->assertDatabaseHas('invoice_activity_logs', [
            'invoice_id' => $invoice->id,
            'user_id' => $actor->id,
            'action' => 'invoice.created',
        ]);
    }

    public function test_updates_a_draft_invoice_and_recalculates_totals(): void
    {
        $actor = User::factory()->create();
        $customer = BillingCustomer::factory()->create();
        $invoice = app(CreateInvoice::class)->execute($this->invoiceData($customer->id), $actor);

        $customer->update(['city' => 'Morges']);
        $data = $this->invoiceData($customer->id, [
            ['type' => 'fee', 'description' => 'Cotisation', 'quantity' => '1', 'unit_price' => '200.00'],
        ]);

        $updated = app(UpdateDraftInvoice::class)->execute($invoice, $data, $actor);

        $this->assertSame(20000, $updated->total_cents);
        $this->assertSame('Morges', $updated->recipient_city);
        $this->assertCount(1, $updated->items);
        $this->assertDatabaseHas('invoice_activity_logs', [
            'invoice_id' => $invoice->id,
            'action' => 'invoice.updated',
        ]);
    }

    public function test_rejects_updating_an_issued_invoice(): void
    {
        $actor = User::factory()->create();
        $customer = BillingCustomer::factory()->create();
        $invoice = app(CreateInvoice::class)->execute($this->invoiceData($customer->id), $actor);
        app(IssueInvoice::class)->execute($invoice, $actor);

        $this->expectException(InvoiceNotDraft::class);

        app(UpdateDraftInvoice::class)->execute($invoice->fresh(), $this->invoiceData($customer->id), $actor);
    }

    public function test_deletes_a_draft_invoice_but_not_an_issued_invoice(): void
    {
        $actor = User::factory()->create();
        $customer = BillingCustomer::factory()->create();
        $draft = app(CreateInvoice::class)->execute($this->invoiceData($customer->id), $actor);

        app(DeleteDraftInvoice::class)->execute($draft, $actor);

        $this->assertModelMissing($draft);

        $issued = app(CreateInvoice::class)->execute($this->invoiceData($customer->id), $actor);
        app(IssueInvoice::class)->execute($issued, $actor);

        $this->expectException(LogicException::class);
        $issued->fresh()->delete();
    }

    public function test_issues_a_draft_invoice_with_a_number_and_ledger_entry(): void
    {
        Event::fake([InvoiceIssued::class]);
        $actor = User::factory()->create();
        $customer = BillingCustomer::factory()->create();
        $invoice = app(CreateInvoice::class)->execute($this->invoiceData($customer->id), $actor);

        $issued = app(IssueInvoice::class)->execute($invoice, $actor);

        $this->assertSame(InvoiceStatus::Issued, $issued->status);
        $this->assertSame('2027-0001', $issued->invoice_number);
        $this->assertNotNull($issued->issued_at);
        $this->assertNotNull($issued->issue_date);
        $this->assertDatabaseHas('customer_account_entries', [
            'invoice_id' => $issued->id,
            'type' => CustomerAccountEntryType::InvoiceIssued->value,
            'debit_cents' => 12000,
        ]);
        Event::assertDispatched(InvoiceIssued::class);
    }

    public function test_issued_invoices_and_lines_are_immutable(): void
    {
        $actor = User::factory()->create();
        $customer = BillingCustomer::factory()->create();
        $invoice = app(CreateInvoice::class)->execute($this->invoiceData($customer->id), $actor);
        $issued = app(IssueInvoice::class)->execute($invoice, $actor);

        $this->expectException(LogicException::class);
        $issued->fresh()->update(['recipient_name' => 'Changed']);
    }

    public function test_issued_invoice_lines_are_immutable(): void
    {
        $actor = User::factory()->create();
        $customer = BillingCustomer::factory()->create();
        $invoice = app(CreateInvoice::class)->execute($this->invoiceData($customer->id), $actor);
        app(IssueInvoice::class)->execute($invoice, $actor);

        $item = InvoiceItem::where('invoice_id', $invoice->id)->firstOrFail();

        $this->expectException(LogicException::class);
        $item->update(['description' => 'Changed']);
    }

    public function test_rejects_issue_without_lines(): void
    {
        $actor = User::factory()->create();
        $customer = BillingCustomer::factory()->create();
        $invoice = app(CreateInvoice::class)->execute($this->invoiceData($customer->id), $actor);
        $invoice->items()->delete();

        $this->expectException(InvoiceNotIssuable::class);

        app(IssueInvoice::class)->execute($invoice, $actor);
    }

    public function test_cancels_an_issued_invoice_and_keeps_its_number(): void
    {
        Event::fake([InvoiceCancelled::class]);
        $actor = User::factory()->create();
        $customer = BillingCustomer::factory()->create();
        $invoice = app(CreateInvoice::class)->execute($this->invoiceData($customer->id), $actor);
        $issued = app(IssueInvoice::class)->execute($invoice, $actor);
        $number = $issued->invoice_number;

        $cancelled = app(CancelInvoice::class)->execute($issued, 'Client parti', $actor);

        $this->assertSame(InvoiceStatus::Cancelled, $cancelled->status);
        $this->assertSame($number, $cancelled->invoice_number);
        $this->assertSame('Client parti', $cancelled->cancellation_reason);
        $this->assertSame($actor->id, $cancelled->cancelled_by);
        $this->assertDatabaseHas('customer_account_entries', [
            'invoice_id' => $cancelled->id,
            'type' => CustomerAccountEntryType::InvoiceCancelled->value,
            'credit_cents' => 12000,
        ]);
        Event::assertDispatched(InvoiceCancelled::class);
    }

    public function test_rejects_cancellation_without_reason(): void
    {
        $actor = User::factory()->create();
        $customer = BillingCustomer::factory()->create();
        $invoice = app(CreateInvoice::class)->execute($this->invoiceData($customer->id), $actor);
        $issued = app(IssueInvoice::class)->execute($invoice, $actor);

        $this->expectException(InvoiceNotCancellable::class);

        app(CancelInvoice::class)->execute($issued, '   ', $actor);
    }

    public function test_rejects_cancellation_of_a_draft(): void
    {
        $actor = User::factory()->create();
        $customer = BillingCustomer::factory()->create();
        $invoice = app(CreateInvoice::class)->execute($this->invoiceData($customer->id), $actor);

        $this->expectException(InvoiceNotCancellable::class);

        app(CancelInvoice::class)->execute($invoice, 'Reason', $actor);
    }

    public function test_rejects_cancellation_when_a_payment_is_allocated(): void
    {
        $actor = User::factory()->create();
        $customer = BillingCustomer::factory()->create();
        $invoice = app(CreateInvoice::class)->execute($this->invoiceData($customer->id), $actor);
        $issued = app(IssueInvoice::class)->execute($invoice, $actor);
        $payment = Payment::factory()->create();
        InvoicePayment::factory()->create([
            'invoice_id' => $issued->id,
            'payment_id' => $payment->id,
            'amount_cents' => 100,
        ]);

        $this->expectException(InvoiceNotCancellable::class);

        app(CancelInvoice::class)->execute($issued, 'Reason', $actor);
    }

    public function test_calculates_outstanding_from_ledger(): void
    {
        $actor = User::factory()->create();
        $customer = BillingCustomer::factory()->create();
        $invoice = app(CreateInvoice::class)->execute($this->invoiceData($customer->id), $actor);
        $issued = app(IssueInvoice::class)->execute($invoice, $actor);

        $this->assertSame(12000, app(CustomerAccountLedger::class)->invoiceOutstandingCents($issued));
    }

    /**
     * @param  array<int, array<string, mixed>>|null  $items
     * @return array<string, mixed>
     */
    private function invoiceData(int $customerId, ?array $items = null): array
    {
        return [
            'billing_customer_id' => $customerId,
            'delivery_method' => 'email',
            'issue_date' => '2027-01-15',
            'due_date' => '2027-02-14',
            'items' => $items ?? [
                ['type' => 'fee', 'description' => 'Cotisation', 'quantity' => '1', 'unit_price' => '150.00'],
                ['type' => 'discount', 'description' => 'Remise', 'quantity' => '1', 'unit_price' => '-30.00'],
            ],
        ];
    }
}
