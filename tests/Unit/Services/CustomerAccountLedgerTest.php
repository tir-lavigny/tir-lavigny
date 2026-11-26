<?php

namespace Tests\Unit\Services;

use App\Domains\Invoicing\Enums\CustomerAccountEntryType;
use App\Domains\Invoicing\Models\BillingCustomer;
use App\Domains\Invoicing\Models\CreditNote;
use App\Domains\Invoicing\Models\CustomerAccountEntry;
use App\Domains\Invoicing\Models\Invoice;
use App\Domains\Invoicing\Services\CustomerAccountLedger;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CustomerAccountLedgerTest extends TestCase
{
    use RefreshDatabase;

    public function test_derives_invoice_outstanding_and_credit_from_entries(): void
    {
        $actor = User::factory()->create();
        $customer = BillingCustomer::factory()->create();
        $invoice = Invoice::factory()->create([
            'billing_customer_id' => $customer->id,
            'total_cents' => 10000,
        ]);
        $creditNote = CreditNote::factory()->create([
            'invoice_id' => $invoice->id,
            'billing_customer_id' => $customer->id,
            'total_cents' => 5000,
        ]);

        $this->entry($customer, $actor, CustomerAccountEntryType::InvoiceIssued, debit: 10000, invoice: $invoice);
        $this->entry($customer, $actor, CustomerAccountEntryType::PaymentAllocated, credit: 3000, invoice: $invoice);
        $this->entry($customer, $actor, CustomerAccountEntryType::CreditNoteIssued, credit: 5000, creditNote: $creditNote);
        $this->entry($customer, $actor, CustomerAccountEntryType::CreditApplied, debit: 2000, creditNote: $creditNote);
        $this->entry($customer, $actor, CustomerAccountEntryType::CreditApplied, credit: 2000, invoice: $invoice);
        $this->entry($customer, $actor, CustomerAccountEntryType::CreditNoteRefunded, debit: 1000, creditNote: $creditNote);
        $this->entry($customer, $actor, CustomerAccountEntryType::CreditNoteReversed, debit: 500, creditNote: $creditNote);

        $ledger = app(CustomerAccountLedger::class);

        $this->assertSame(5000, $ledger->invoiceOutstandingCents($invoice));
        $this->assertSame(1500, $ledger->availableCreditCents($creditNote));
        $this->assertSame(1500, $ledger->customerAvailableCreditCents($customer));
    }

    public function test_invoice_outstanding_falls_back_to_the_invoice_total_without_entries(): void
    {
        $invoice = Invoice::factory()->create(['total_cents' => 12000]);

        $this->assertSame(12000, app(CustomerAccountLedger::class)->invoiceOutstandingCents($invoice));
    }

    private function entry(
        BillingCustomer $customer,
        User $actor,
        CustomerAccountEntryType $type,
        int $debit = 0,
        int $credit = 0,
        ?Invoice $invoice = null,
        ?CreditNote $creditNote = null,
    ): CustomerAccountEntry {
        return CustomerAccountEntry::create([
            'billing_customer_id' => $customer->id,
            'invoice_id' => $invoice?->id,
            'credit_note_id' => $creditNote?->id,
            'type' => $type,
            'debit_cents' => $debit,
            'credit_cents' => $credit,
            'occurred_at' => now(),
            'created_by' => $actor->id,
            'metadata' => null,
        ]);
    }
}
