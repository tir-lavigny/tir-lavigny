<?php

namespace Tests\Feature\BackOffice\Invoicing;

use App\Domains\Invoicing\Actions\CreditNotes\CreateCreditNote;
use App\Domains\Invoicing\Actions\CreditNotes\IssueCreditNote;
use App\Domains\Invoicing\Actions\CreditNotes\ReverseCreditNoteApplication;
use App\Domains\Invoicing\Actions\Invoices\CreateInvoice;
use App\Domains\Invoicing\Actions\Invoices\IssueInvoice;
use App\Domains\Invoicing\Models\BillingCustomer;
use App\Domains\Invoicing\Models\CreditNote;
use App\Domains\Invoicing\Models\CreditNoteReversal;
use App\Domains\Invoicing\Models\Invoice;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CreditNoteOperationsControllerTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);
    }

    public function test_applies_credit_to_another_invoice_of_the_same_customer(): void
    {
        $this->actingAsAdmin();
        $actor = User::factory()->create();
        $customer = BillingCustomer::factory()->create();
        $invoiceA = $this->issuedInvoice($customer, $actor, '100.00');
        $invoiceB = $this->issuedInvoice($customer, $actor, '100.00');
        $creditNote = $this->issuedCreditNote($invoiceA, $actor, '50.00');
        $this->reverseAutoApplication($creditNote, $actor);

        $response = $this->post(route('bo.invoicing.credit-notes.apply', $creditNote), [
            'invoice_id' => $invoiceB->id,
            'amount' => '50.00',
        ]);

        $response->assertSessionHas('success');
        $this->assertSame(5000, (int) $invoiceB->fresh()->creditNoteApplications()->sum('amount_cents'));
    }

    public function test_rejects_application_to_another_customers_invoice(): void
    {
        $this->actingAsAdmin();
        $actor = User::factory()->create();
        $customerA = BillingCustomer::factory()->create();
        $customerB = BillingCustomer::factory()->create();
        $invoiceA = $this->issuedInvoice($customerA, $actor, '100.00');
        $invoiceB = $this->issuedInvoice($customerB, $actor, '100.00');
        $creditNote = $this->issuedCreditNote($invoiceA, $actor, '50.00');
        $this->reverseAutoApplication($creditNote, $actor);

        $this->post(route('bo.invoicing.credit-notes.apply', $creditNote), [
            'invoice_id' => $invoiceB->id,
            'amount' => '50.00',
        ])->assertSessionHasErrors('amount');

        $this->assertSame(0, (int) $invoiceB->fresh()->creditNoteApplications()->sum('amount_cents'));
    }

    public function test_records_a_refund(): void
    {
        $this->actingAsAdmin();
        $actor = User::factory()->create();
        $invoice = $this->issuedInvoice(BillingCustomer::factory()->create(), $actor, '100.00');
        $creditNote = $this->issuedCreditNote($invoice, $actor, '50.00');
        $this->reverseAutoApplication($creditNote, $actor);

        $this->post(route('bo.invoicing.credit-notes.refund', $creditNote), [
            'amount' => '30.00',
            'refund_date' => '2027-02-01',
            'payment_method' => 'bank_transfer',
        ])->assertSessionHas('success');

        $this->assertSame(3000, (int) $creditNote->fresh()->refunds()->sum('amount_cents'));
    }

    public function test_creates_and_issues_a_reversal_document(): void
    {
        $this->actingAsAdmin();
        $actor = User::factory()->create();
        $invoice = $this->issuedInvoice(BillingCustomer::factory()->create(), $actor, '100.00');
        $creditNote = $this->issuedCreditNote($invoice, $actor, '50.00');
        $this->reverseAutoApplication($creditNote, $actor);

        $this->post(route('bo.invoicing.credit-notes.reversals.store', $creditNote), [
            'reason' => 'Correction',
            'issue_date' => '2027-03-01',
            'items' => [
                ['description' => 'Annulation partielle', 'amount' => '20.00'],
            ],
        ])->assertRedirect(route('bo.invoicing.credit-notes.show', $creditNote))
            ->assertSessionHas('success');

        $reversal = CreditNoteReversal::firstOrFail();

        $this->post(route('bo.invoicing.credit-note-reversals.issue', $reversal))
            ->assertSessionHas('success');

        $this->assertSame('CNR-2027-0001', $reversal->fresh()->credit_note_reversal_number);
    }

    public function test_rejects_a_refund_above_the_available_credit(): void
    {
        $this->actingAsAdmin();
        $actor = User::factory()->create();
        $invoice = $this->issuedInvoice(BillingCustomer::factory()->create(), $actor, '100.00');
        $creditNote = $this->issuedCreditNote($invoice, $actor, '50.00');
        $this->reverseAutoApplication($creditNote, $actor);

        $this->post(route('bo.invoicing.credit-notes.refund', $creditNote), [
            'amount' => '60.00',
            'refund_date' => '2027-02-01',
            'payment_method' => 'bank_transfer',
        ])->assertSessionHasErrors('amount');

        $this->assertSame(0, $creditNote->fresh()->refunds()->count());
    }

    public function test_rejects_a_reversal_when_credit_note_is_fully_used(): void
    {
        $this->actingAsAdmin();
        $actor = User::factory()->create();
        $invoice = $this->issuedInvoice(BillingCustomer::factory()->create(), $actor, '100.00');
        $creditNote = $this->issuedCreditNote($invoice, $actor, '100.00');

        $this->post(route('bo.invoicing.credit-notes.reversals.store', $creditNote), [
            'reason' => 'Correction',
            'items' => [['description' => 'Annulation', 'amount' => '10.00']],
        ])->assertSessionHasErrors('items');

        $this->assertDatabaseCount('credit_note_reversals', 0);
    }

    public function test_allows_reversal_of_remaining_credit_after_refund(): void
    {
        $this->actingAsAdmin();
        $actor = User::factory()->create();
        $invoice = $this->issuedInvoice(BillingCustomer::factory()->create(), $actor, '50.00');
        $creditNote = $this->issuedCreditNote($invoice, $actor, '50.00');
        $this->reverseAutoApplication($creditNote, $actor);

        $this->post(route('bo.invoicing.credit-notes.refund', $creditNote), [
            'amount' => '46.00',
            'refund_date' => '2027-02-01',
            'payment_method' => 'cash',
        ])->assertSessionHas('success');

        $this->post(route('bo.invoicing.credit-notes.reversals.store', $creditNote), [
            'reason' => 'Solde',
            'issue_date' => '2027-03-01',
            'items' => [['description' => 'Solde', 'amount' => '4.00']],
        ])->assertSessionHas('success');

        $reversal = CreditNoteReversal::firstOrFail();

        $this->post(route('bo.invoicing.credit-note-reversals.issue', $reversal))
            ->assertSessionHas('success');

        $this->assertSame('CNR-2027-0001', $reversal->fresh()->credit_note_reversal_number);
    }

    private function actingAsAdmin(): User
    {
        $admin = User::factory()->create();
        $admin->assignRole('admin');
        $this->actingAs($admin);

        return $admin;
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
            'items' => [['description' => 'Avoir', 'quantity' => '1', 'unit_price' => $amount]],
        ], $actor);

        return app(IssueCreditNote::class)->execute($creditNote, $actor);
    }

    private function reverseAutoApplication(CreditNote $creditNote, User $actor): void
    {
        $application = $creditNote->applications()->firstOrFail();

        app(ReverseCreditNoteApplication::class)->execute(
            $application,
            $application->amount_cents,
            'Recredit',
            $actor,
        );
    }
}
