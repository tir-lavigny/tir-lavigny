<?php

namespace Tests\Unit\Actions\CreditNotes;

use App\Domains\Invoicing\Actions\CreditNotes\ApplyCreditNoteToInvoice;
use App\Domains\Invoicing\Actions\CreditNotes\CreateCreditNote;
use App\Domains\Invoicing\Actions\CreditNotes\CreateCreditNoteReversal;
use App\Domains\Invoicing\Actions\CreditNotes\IssueCreditNote;
use App\Domains\Invoicing\Actions\CreditNotes\IssueCreditNoteReversal;
use App\Domains\Invoicing\Actions\CreditNotes\RecordCreditNoteRefund;
use App\Domains\Invoicing\Actions\CreditNotes\ReverseCreditNoteApplication;
use App\Domains\Invoicing\Actions\Invoices\CreateInvoice;
use App\Domains\Invoicing\Actions\Invoices\IssueInvoice;
use App\Domains\Invoicing\Enums\CreditNoteStatus;
use App\Domains\Invoicing\Exceptions\CreditNoteNotApplicable;
use App\Domains\Invoicing\Models\BillingCustomer;
use App\Domains\Invoicing\Models\CreditNote;
use App\Domains\Invoicing\Models\CreditNoteApplication;
use App\Domains\Invoicing\Models\Invoice;
use App\Domains\Invoicing\Services\CreditNoteBalanceCalculator;
use App\Domains\Invoicing\Services\CustomerAccountLedger;
use App\Domains\Invoicing\Services\InvoiceBalanceCalculator;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CreditNoteOperationsTest extends TestCase
{
    use RefreshDatabase;

    public function test_applies_credit_to_another_invoice_of_the_same_customer(): void
    {
        $actor = User::factory()->create();
        $customer = BillingCustomer::factory()->create();
        $invoiceA = $this->issuedInvoice($customer, $actor, '100.00');
        $invoiceB = $this->issuedInvoice($customer, $actor, '100.00');
        $creditNote = $this->issuedCreditNote($invoiceA, $actor, '50.00');
        $application = $creditNote->applications()->firstOrFail();
        app(ReverseCreditNoteApplication::class)->execute($application, 5000, 'Recredit', $actor);

        $applied = app(ApplyCreditNoteToInvoice::class)->execute($creditNote->fresh(), $invoiceB, 5000, $actor);

        $this->assertSame(5000, $applied->amount_cents);
        $this->assertSame(5000, (int) $invoiceB->fresh()->creditNoteApplications()->sum('amount_cents'));
        $this->assertSame(5000, app(InvoiceBalanceCalculator::class)->outstandingCents($invoiceB->fresh()));
    }

    public function test_rejects_application_to_another_customers_invoice(): void
    {
        $actor = User::factory()->create();
        $customerA = BillingCustomer::factory()->create();
        $customerB = BillingCustomer::factory()->create();
        $invoiceA = $this->issuedInvoice($customerA, $actor, '100.00');
        $invoiceB = $this->issuedInvoice($customerB, $actor, '100.00');
        $creditNote = $this->issuedCreditNote($invoiceA, $actor, '50.00');
        $application = $creditNote->applications()->firstOrFail();
        app(ReverseCreditNoteApplication::class)->execute($application, 5000, 'Recredit', $actor);

        $this->expectException(CreditNoteNotApplicable::class);

        app(ApplyCreditNoteToInvoice::class)->execute($creditNote->fresh(), $invoiceB, 5000, $actor);
    }

    public function test_rejects_over_application(): void
    {
        $actor = User::factory()->create();
        $customer = BillingCustomer::factory()->create();
        $invoiceA = $this->issuedInvoice($customer, $actor, '100.00');
        $invoiceB = $this->issuedInvoice($customer, $actor, '100.00');
        $creditNote = $this->issuedCreditNote($invoiceA, $actor, '50.00');
        $application = $creditNote->applications()->firstOrFail();
        app(ReverseCreditNoteApplication::class)->execute($application, 5000, 'Recredit', $actor);

        $this->expectException(CreditNoteNotApplicable::class);

        app(ApplyCreditNoteToInvoice::class)->execute($creditNote->fresh(), $invoiceB, 6000, $actor);
    }

    public function test_records_refunds_within_available_credit(): void
    {
        $actor = User::factory()->create();
        $invoice = $this->issuedInvoice(BillingCustomer::factory()->create(), $actor, '100.00');
        $creditNote = $this->issuedCreditNote($invoice, $actor, '50.00');
        $application = $creditNote->applications()->firstOrFail();
        app(ReverseCreditNoteApplication::class)->execute($application, 5000, 'Recredit', $actor);

        $refund = app(RecordCreditNoteRefund::class)->execute($creditNote->fresh(), [
            'amount_cents' => 3000,
            'refund_date' => '2027-02-01',
            'payment_method' => 'bank_transfer',
            'reference' => null,
            'notes' => null,
        ], $actor);

        $this->assertSame(3000, $refund->amount_cents);
        $this->assertSame(2000, app(CreditNoteBalanceCalculator::class)->availableCents($creditNote->fresh()));

        $this->expectException(CreditNoteNotApplicable::class);
        app(RecordCreditNoteRefund::class)->execute($creditNote->fresh(), [
            'amount_cents' => 3000,
            'refund_date' => '2027-02-02',
            'payment_method' => 'bank_transfer',
        ], $actor);
    }

    public function test_issues_a_reversal_with_its_own_number(): void
    {
        $actor = User::factory()->create();
        $invoice = $this->issuedInvoice(BillingCustomer::factory()->create(), $actor, '100.00');
        $creditNote = $this->issuedCreditNote($invoice, $actor, '50.00');
        $application = $creditNote->applications()->firstOrFail();
        app(ReverseCreditNoteApplication::class)->execute($application, 5000, 'Recredit', $actor);

        $reversal = app(CreateCreditNoteReversal::class)->execute($creditNote->fresh(), [
            'reason' => 'Correction',
            'issue_date' => '2027-03-01',
            'items' => [
                ['description' => 'Annulation partielle', 'amount' => '20.00'],
            ],
        ], $actor);

        $issued = app(IssueCreditNoteReversal::class)->execute($reversal, $actor);

        $this->assertSame('CNR-2027-0001', $issued->credit_note_reversal_number);
        $this->assertSame(2000, $issued->total_cents);
        $this->assertSame(CreditNoteStatus::PartiallyReversed, $creditNote->fresh()->status);
        $this->assertSame(3000, app(CreditNoteBalanceCalculator::class)->availableCents($creditNote->fresh()));
        $this->assertSame(3000, app(CustomerAccountLedger::class)->availableCreditCents($creditNote->fresh()));
    }

    public function test_blocks_reversal_while_applications_remain(): void
    {
        $actor = User::factory()->create();
        $invoice = $this->issuedInvoice(BillingCustomer::factory()->create(), $actor, '100.00');
        $creditNote = $this->issuedCreditNote($invoice, $actor, '100.00');
        $application = $creditNote->applications()->firstOrFail();
        app(ReverseCreditNoteApplication::class)->execute($application, 10000, 'Recredit', $actor);

        CreditNoteApplication::create([
            'credit_note_id' => $creditNote->id,
            'invoice_id' => $invoice->id,
            'amount_cents' => 1000,
            'applied_at' => now(),
            'created_by' => $actor->id,
        ]);

        $this->expectException(CreditNoteNotApplicable::class);
        app(CreateCreditNoteReversal::class)->execute($creditNote->fresh(), [
            'reason' => 'Correction',
            'items' => [['description' => 'Annulation', 'amount' => '10.00']],
        ], $actor);
    }

    public function test_allows_reversal_of_remaining_credit_after_refund(): void
    {
        $actor = User::factory()->create();
        $invoice = $this->issuedInvoice(BillingCustomer::factory()->create(), $actor, '50.00');
        $creditNote = $this->issuedCreditNote($invoice, $actor, '50.00');
        $application = $creditNote->applications()->firstOrFail();
        app(ReverseCreditNoteApplication::class)->execute($application, 5000, 'Recredit', $actor);

        app(RecordCreditNoteRefund::class)->execute($creditNote->fresh(), [
            'amount_cents' => 4600,
            'refund_date' => '2027-02-01',
            'payment_method' => 'cash',
        ], $actor);

        $this->assertSame(400, app(CreditNoteBalanceCalculator::class)->availableCents($creditNote->fresh()));

        $reversal = app(CreateCreditNoteReversal::class)->execute($creditNote->fresh(), [
            'reason' => 'Correction',
            'issue_date' => '2027-03-01',
            'items' => [['description' => 'Solde', 'amount' => '4.00']],
        ], $actor);

        $issued = app(IssueCreditNoteReversal::class)->execute($reversal, $actor);

        $this->assertSame('CNR-2027-0001', $issued->credit_note_reversal_number);
        $this->assertSame(0, app(CreditNoteBalanceCalculator::class)->availableCents($creditNote->fresh()));
        $this->assertSame(CreditNoteStatus::PartiallyReversed, $creditNote->fresh()->status);
    }

    public function test_recalculates_balances_from_records(): void
    {
        $actor = User::factory()->create();
        $invoice = $this->issuedInvoice(BillingCustomer::factory()->create(), $actor, '100.00');
        $creditNote = $this->issuedCreditNote($invoice, $actor, '100.00');
        $application = $creditNote->applications()->firstOrFail();

        $this->assertSame(0, app(CustomerAccountLedger::class)->invoiceOutstandingCents($invoice->fresh()));
        $this->assertSame(0, app(CustomerAccountLedger::class)->availableCreditCents($creditNote->fresh()));

        app(ReverseCreditNoteApplication::class)->execute($application, 10000, 'Recredit', $actor);

        $this->assertSame(10000, app(InvoiceBalanceCalculator::class)->outstandingCents($invoice->fresh()));
        $this->assertSame(10000, app(CustomerAccountLedger::class)->availableCreditCents($creditNote->fresh()));
    }

    private function issuedInvoice(BillingCustomer $customer, User $actor, string $amount): Invoice
    {
        $invoice = app(CreateInvoice::class)->execute([
            'billing_customer_id' => $customer->id,
            'delivery_method' => 'email',
            'issue_date' => '2027-01-15',
            'due_date' => '2027-02-14',
            'items' => [
                ['type' => 'fee', 'description' => 'Prestation', 'quantity' => '1', 'unit_price' => $amount],
            ],
        ], $actor);

        return app(IssueInvoice::class)->execute($invoice, $actor);
    }

    private function issuedCreditNote(Invoice $invoice, User $actor, string $amount): CreditNote
    {
        $creditNote = app(CreateCreditNote::class)->execute([
            'invoice_id' => $invoice->id,
            'reason' => 'Correction',
            'issue_date' => '2027-01-20',
            'items' => [
                ['description' => 'Avoir', 'quantity' => '1', 'unit_price' => $amount],
            ],
        ], $actor);

        return app(IssueCreditNote::class)->execute($creditNote, $actor);
    }
}
