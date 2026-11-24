<?php

namespace Tests\Unit\Actions\CreditNotes;

use App\Domains\Invoicing\Actions\CreditNotes\CreateCreditNote;
use App\Domains\Invoicing\Actions\CreditNotes\IssueCreditNote;
use App\Domains\Invoicing\Actions\Invoices\CreateInvoice;
use App\Domains\Invoicing\Actions\Invoices\IssueInvoice;
use App\Domains\Invoicing\Enums\CreditNoteStatus;
use App\Domains\Invoicing\Enums\InvoiceStatus;
use App\Domains\Invoicing\Exceptions\CreditNoteNotIssuable;
use App\Domains\Invoicing\Models\BillingCustomer;
use App\Domains\Invoicing\Models\CreditNoteApplication;
use App\Domains\Invoicing\Models\Invoice;
use App\Domains\Invoicing\Services\CreditNoteBalanceCalculator;
use App\Domains\Invoicing\Services\CreditNoteCreditLimit;
use App\Domains\Invoicing\Services\CustomerAccountLedger;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use LogicException;
use Tests\TestCase;

class CreditNoteActionsTest extends TestCase
{
    use RefreshDatabase;

    public function test_creates_and_issues_a_full_credit_note_and_applies_it_to_the_original_invoice(): void
    {
        $actor = User::factory()->create();
        $invoice = $this->issuedInvoice($actor, '120.00');

        $creditNote = app(CreateCreditNote::class)->execute($this->creditNoteData($invoice, '120.00'), $actor);

        $this->assertSame(CreditNoteStatus::Draft, $creditNote->status);
        $this->assertNull($creditNote->credit_note_number);
        $this->assertSame(12000, $creditNote->total_cents);

        $issued = app(IssueCreditNote::class)->execute($creditNote, $actor);

        $this->assertSame(CreditNoteStatus::FullyUsed, $issued->status);
        $this->assertSame('CN-2027-0001', $issued->credit_note_number);
        $this->assertNotNull($issued->issued_at);
        $this->assertSame(12000, CreditNoteApplication::where('credit_note_id', $issued->id)->sum('amount_cents'));
        $this->assertSame(InvoiceStatus::Paid, $invoice->fresh()->status);
        $this->assertSame(0, app(CreditNoteBalanceCalculator::class)->availableCents($issued));
        $this->assertSame(0, app(CustomerAccountLedger::class)->invoiceOutstandingCents($invoice->fresh()));
    }

    public function test_creates_a_partial_credit_note_and_leaves_the_invoice_partially_paid(): void
    {
        $actor = User::factory()->create();
        $invoice = $this->issuedInvoice($actor, '120.00');

        $creditNote = app(CreateCreditNote::class)->execute($this->creditNoteData($invoice, '50.00'), $actor);
        $issued = app(IssueCreditNote::class)->execute($creditNote, $actor);

        $this->assertSame(CreditNoteStatus::FullyUsed, $issued->status);
        $this->assertSame(5000, CreditNoteApplication::where('credit_note_id', $issued->id)->sum('amount_cents'));
        $this->assertSame(InvoiceStatus::PartiallyPaid, $invoice->fresh()->status);
        $this->assertSame(7000, app(CustomerAccountLedger::class)->invoiceOutstandingCents($invoice->fresh()));
        $this->assertSame(0, app(CreditNoteBalanceCalculator::class)->availableCents($issued));
    }

    public function test_rejects_a_credit_note_exceeding_the_remaining_creditable_amount(): void
    {
        $actor = User::factory()->create();
        $invoice = $this->issuedInvoice($actor, '100.00');

        $creditNote = app(CreateCreditNote::class)->execute($this->creditNoteData($invoice, '110.00'), $actor);

        $this->expectException(CreditNoteNotIssuable::class);

        app(IssueCreditNote::class)->execute($creditNote, $actor);
    }

    public function test_tracks_remaining_creditable_amount_after_an_issued_credit_note(): void
    {
        $actor = User::factory()->create();
        $invoice = $this->issuedInvoice($actor, '120.00');
        $first = app(CreateCreditNote::class)->execute($this->creditNoteData($invoice, '50.00'), $actor);
        app(IssueCreditNote::class)->execute($first, $actor);

        $this->assertSame(7000, app(CreditNoteCreditLimit::class)->remainingFor($invoice->fresh()));

        $second = app(CreateCreditNote::class)->execute($this->creditNoteData($invoice, '80.00'), $actor);

        $this->expectException(CreditNoteNotIssuable::class);
        app(IssueCreditNote::class)->execute($second, $actor);
    }

    public function test_allocates_separate_sequential_credit_note_numbers(): void
    {
        $actor = User::factory()->create();
        $firstInvoice = $this->issuedInvoice($actor, '100.00');
        $secondInvoice = $this->issuedInvoice($actor, '100.00');

        $first = app(CreateCreditNote::class)->execute($this->creditNoteData($firstInvoice, '100.00'), $actor);
        $second = app(CreateCreditNote::class)->execute($this->creditNoteData($secondInvoice, '100.00'), $actor);

        $this->assertSame('CN-2027-0001', app(IssueCreditNote::class)->execute($first, $actor)->credit_note_number);
        $this->assertSame('CN-2027-0002', app(IssueCreditNote::class)->execute($second, $actor)->credit_note_number);
    }

    public function test_issued_credit_notes_and_lines_are_immutable(): void
    {
        $actor = User::factory()->create();
        $invoice = $this->issuedInvoice($actor, '100.00');
        $creditNote = app(CreateCreditNote::class)->execute($this->creditNoteData($invoice, '50.00'), $actor);
        $issued = app(IssueCreditNote::class)->execute($creditNote, $actor);

        $this->expectException(LogicException::class);
        $issued->update(['reason' => 'Changed']);
    }

    private function issuedInvoice(User $actor, string $amount): Invoice
    {
        $customer = BillingCustomer::factory()->create();
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

    /**
     * @return array<string, mixed>
     */
    private function creditNoteData(Invoice $invoice, string $amount): array
    {
        return [
            'invoice_id' => $invoice->id,
            'reason' => 'Correction',
            'issue_date' => '2027-01-20',
            'items' => [
                ['description' => 'Avoir', 'quantity' => '1', 'unit_price' => $amount],
            ],
        ];
    }
}
