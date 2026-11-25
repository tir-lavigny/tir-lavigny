<?php

namespace Tests\Unit\Actions\Payments;

use App\Domains\Invoicing\Actions\CreditNotes\CreateCreditNote;
use App\Domains\Invoicing\Actions\CreditNotes\IssueCreditNote;
use App\Domains\Invoicing\Actions\Invoices\CancelInvoice;
use App\Domains\Invoicing\Actions\Payments\AllocatePayment;
use App\Domains\Invoicing\Actions\Payments\RegisterPayment;
use App\Domains\Invoicing\Actions\Payments\ReversePaymentAllocation;
use App\Domains\Invoicing\Actions\Payments\UpdatePayment;
use App\Domains\Invoicing\Actions\Payments\VoidPayment;
use App\Domains\Invoicing\Enums\CustomerAccountEntryType;
use App\Domains\Invoicing\Enums\InvoiceStatus;
use App\Domains\Invoicing\Enums\PaymentMethod;
use App\Domains\Invoicing\Exceptions\PaymentException;
use App\Domains\Invoicing\Models\Invoice;
use App\Domains\Invoicing\Models\InvoicePayment;
use App\Domains\Invoicing\Models\Payment;
use App\Domains\Invoicing\Services\CustomerAccountLedger;
use App\Domains\Invoicing\Services\InvoiceBalanceCalculator;
use App\Domains\Invoicing\Services\PaymentBalanceCalculator;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\Support\IssuesInvoices;
use Tests\TestCase;

class PaymentActionsTest extends TestCase
{
    use IssuesInvoices;
    use RefreshDatabase;

    private User $actor;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);
        $this->actor = $this->invoicingAdmin();
    }

    public function test_registers_an_unallocated_payment_once_per_idempotency_key(): void
    {
        $data = $this->paymentData(10000, 'PAY-001');

        $payment = app(RegisterPayment::class)->execute($data, $this->actor);
        $again = app(RegisterPayment::class)->execute($data, $this->actor);

        $this->assertSame($payment->id, $again->id);
        $this->assertSame(PaymentMethod::BankTransfer, $payment->method);
        $this->assertSame('CHF', $payment->currency->value);
        $this->assertSame($this->actor->id, $payment->created_by);
        $this->assertSame(10000, app(PaymentBalanceCalculator::class)->availableCents($payment));
        $this->assertDatabaseCount('payments', 1);
    }

    public function test_allocating_a_partial_payment_updates_invoice_status_and_ledger(): void
    {
        $invoice = $this->issueInvoice(actor: $this->actor);
        $payment = $this->registeredPayment(10000);

        $allocation = app(AllocatePayment::class)->execute(
            $payment,
            $invoice,
            5000,
            $this->actor,
            (string) Str::uuid(),
        );

        $invoice->refresh();

        $this->assertSame(InvoiceStatus::PartiallyPaid, $invoice->status);
        $this->assertSame(5000, app(InvoiceBalanceCalculator::class)->paidCents($invoice));
        $this->assertSame(10000, app(InvoiceBalanceCalculator::class)->outstandingCents($invoice));
        $this->assertSame(10000, app(CustomerAccountLedger::class)->invoiceOutstandingCents($invoice));
        $this->assertSame(5000, app(PaymentBalanceCalculator::class)->availableCents($payment));
        $this->assertGreaterThan(0, $allocation->id);
        $this->assertDatabaseHas('customer_account_entries', [
            'invoice_id' => $invoice->id,
            'type' => CustomerAccountEntryType::PaymentAllocated->value,
            'credit_cents' => 5000,
        ]);
        $this->assertDatabaseHas('invoice_activity_logs', [
            'invoice_id' => $invoice->id,
            'action' => 'invoice.payment_allocated',
        ]);
    }

    public function test_full_payment_marks_invoice_paid_and_logs_it(): void
    {
        $invoice = $this->issueInvoice(actor: $this->actor);
        $payment = $this->registeredPayment(15000);

        app(AllocatePayment::class)->execute($payment, $invoice, 15000, $this->actor);

        $invoice->refresh();

        $this->assertSame(InvoiceStatus::Paid, $invoice->status);
        $this->assertSame(0, app(InvoiceBalanceCalculator::class)->outstandingCents($invoice));
        $this->assertDatabaseHas('invoice_activity_logs', [
            'invoice_id' => $invoice->id,
            'action' => 'invoice.paid',
        ]);
    }

    public function test_payment_can_be_allocated_across_multiple_invoices(): void
    {
        $first = $this->issueInvoice(actor: $this->actor);
        $second = $this->issueInvoice(actor: $this->actor);
        $payment = $this->registeredPayment(30000);

        app(AllocatePayment::class)->execute($payment, $first, 15000, $this->actor);
        app(AllocatePayment::class)->execute($payment, $second, 15000, $this->actor);

        $this->assertSame(InvoiceStatus::Paid, $first->fresh()->status);
        $this->assertSame(InvoiceStatus::Paid, $second->fresh()->status);
        $this->assertSame(0, app(PaymentBalanceCalculator::class)->availableCents($payment));
        $this->assertSame(2, InvoicePayment::query()->where('payment_id', $payment->id)->count());
    }

    public function test_payment_and_credit_note_together_settle_invoice(): void
    {
        $invoice = $this->issueInvoice(actor: $this->actor);
        $creditNote = app(CreateCreditNote::class)->execute([
            'invoice_id' => $invoice->id,
            'reason' => 'Correction',
            'issue_date' => '2027-01-20',
            'items' => [['description' => 'Avoir', 'quantity' => '1', 'unit_price' => '50.00']],
        ], $this->actor);
        app(IssueCreditNote::class)->execute($creditNote, $this->actor);

        $payment = $this->registeredPayment(10000);
        app(AllocatePayment::class)->execute($payment, $invoice->fresh(), 10000, $this->actor);

        $this->assertSame(InvoiceStatus::Paid, $invoice->fresh()->status);
    }

    public function test_rejects_allocation_above_payment_available_balance(): void
    {
        $invoice = $this->issueInvoice(actor: $this->actor);
        $payment = $this->registeredPayment(5000);

        $this->expectException(PaymentException::class);

        app(AllocatePayment::class)->execute($payment, $invoice, 6000, $this->actor);
    }

    public function test_rejects_allocation_above_invoice_outstanding_balance(): void
    {
        $invoice = $this->issueInvoice(actor: $this->actor);
        $payment = $this->registeredPayment(20000);

        $this->expectException(PaymentException::class);

        app(AllocatePayment::class)->execute($payment, $invoice, 20000, $this->actor);
    }

    public function test_rejects_allocation_to_draft_or_cancelled_invoice(): void
    {
        $payment = $this->registeredPayment(10000);
        $draft = Invoice::factory()->create(['status' => 'draft']);
        $cancelled = Invoice::factory()->create(['status' => 'cancelled']);

        foreach ([$draft, $cancelled] as $invoice) {
            try {
                app(AllocatePayment::class)->execute($payment, $invoice, 1000, $this->actor);
                $this->fail('Allocation should have been rejected.');
            } catch (PaymentException) {
                $this->assertSame(0, InvoicePayment::count());
            }
        }
    }

    public function test_allocation_idempotency_returns_one_allocation(): void
    {
        $invoice = $this->issueInvoice(actor: $this->actor);
        $payment = $this->registeredPayment(10000);
        $key = (string) Str::uuid();

        $first = app(AllocatePayment::class)->execute($payment, $invoice, 5000, $this->actor, $key);
        $second = app(AllocatePayment::class)->execute($payment, $invoice, 5000, $this->actor, $key);

        $this->assertSame($first->id, $second->id);
        $this->assertSame(1, InvoicePayment::count());
    }

    public function test_payment_can_be_allocated_to_the_same_invoice_multiple_times(): void
    {
        $invoice = $this->issueInvoice(actor: $this->actor);
        $payment = $this->registeredPayment(10000);

        app(AllocatePayment::class)->execute($payment, $invoice, 4000, $this->actor, (string) Str::uuid());
        app(AllocatePayment::class)->execute($payment, $invoice, 6000, $this->actor, (string) Str::uuid());

        $this->assertSame(2, InvoicePayment::query()
            ->where('payment_id', $payment->id)
            ->where('invoice_id', $invoice->id)
            ->count());
        $this->assertSame(10000, app(InvoiceBalanceCalculator::class)->paidCents($invoice->fresh()));
        $this->assertSame(5000, app(InvoiceBalanceCalculator::class)->outstandingCents($invoice->fresh()));
    }

    public function test_reverses_allocation_and_allows_corrected_reallocation(): void
    {
        $invoice = $this->issueInvoice(actor: $this->actor);
        $payment = $this->registeredPayment(15000);
        $allocation = app(AllocatePayment::class)->execute($payment, $invoice, 15000, $this->actor);

        app(ReversePaymentAllocation::class)->execute($allocation, 'Mauvais montant', $this->actor);

        $invoice->refresh();
        $this->assertSame(InvoiceStatus::Issued, $invoice->status);
        $this->assertSame(0, app(InvoiceBalanceCalculator::class)->paidCents($invoice));
        $this->assertSame(15000, app(PaymentBalanceCalculator::class)->availableCents($payment));
        $this->assertDatabaseHas('customer_account_entries', [
            'invoice_id' => $invoice->id,
            'type' => CustomerAccountEntryType::PaymentAllocationReversed->value,
            'debit_cents' => 15000,
        ]);
        $this->assertDatabaseHas('invoice_activity_logs', [
            'invoice_id' => $invoice->id,
            'action' => 'invoice.payment_allocation_reversed',
        ]);

        app(UpdatePayment::class)->execute($payment, $this->paymentData(12000, $payment->idempotency_key));
        $payment->refresh();
        app(AllocatePayment::class)->execute($payment, $invoice, 12000, $this->actor);

        $this->assertSame(InvoiceStatus::PartiallyPaid, $invoice->fresh()->status);
        $this->assertSame(12000, app(InvoiceBalanceCalculator::class)->paidCents($invoice->fresh()));
    }

    public function test_rejects_amount_change_with_active_allocation_but_allows_metadata_edit(): void
    {
        $invoice = $this->issueInvoice(actor: $this->actor);
        $payment = $this->registeredPayment(10000);
        app(AllocatePayment::class)->execute($payment, $invoice, 5000, $this->actor);

        try {
            app(UpdatePayment::class)->execute($payment, $this->paymentData(12000, $payment->idempotency_key));
            $this->fail('Amount update should have been rejected.');
        } catch (PaymentException) {
            $this->assertSame(10000, $payment->fresh()->amount_cents);
        }

        $updated = app(UpdatePayment::class)->execute($payment, [
            ...$this->paymentData(10000, $payment->idempotency_key),
            'reference' => 'Corrigé',
            'notes' => 'Référence corrigée',
        ]);

        $this->assertSame('Corrigé', $updated->reference);
        $this->assertSame('Référence corrigée', $updated->notes);
    }

    public function test_voids_unallocated_payment_and_blocks_future_allocation(): void
    {
        $invoice = $this->issueInvoice(actor: $this->actor);
        $payment = $this->registeredPayment(10000);

        $voided = app(VoidPayment::class)->execute($payment, 'Paiement saisi par erreur', $this->actor);

        $this->assertTrue($voided->isVoided());
        $this->assertSame($this->actor->id, $voided->voided_by);
        $this->assertSame(0, app(PaymentBalanceCalculator::class)->availableCents($voided));

        $this->expectException(PaymentException::class);
        app(AllocatePayment::class)->execute($voided, $invoice, 1000, $this->actor);
    }

    public function test_rejects_voiding_payment_with_active_allocation(): void
    {
        $invoice = $this->issueInvoice(actor: $this->actor);
        $payment = $this->registeredPayment(10000);
        app(AllocatePayment::class)->execute($payment, $invoice, 5000, $this->actor);

        $this->expectException(PaymentException::class);

        app(VoidPayment::class)->execute($payment, 'Erreur', $this->actor);
    }

    public function test_invoice_can_be_cancelled_after_all_allocations_are_reversed(): void
    {
        $invoice = $this->issueInvoice(actor: $this->actor);
        $payment = $this->registeredPayment(15000);
        $allocation = app(AllocatePayment::class)->execute($payment, $invoice, 15000, $this->actor);

        app(ReversePaymentAllocation::class)->execute($allocation, 'Erreur', $this->actor);
        $cancelled = app(CancelInvoice::class)->execute($invoice->fresh(), 'Annulation test', $this->actor);

        $this->assertSame(InvoiceStatus::Cancelled, $cancelled->status);
    }

    private function registeredPayment(int $amountCents): Payment
    {
        return app(RegisterPayment::class)->execute(
            $this->paymentData($amountCents),
            $this->actor,
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function paymentData(int $amountCents, ?string $key = null): array
    {
        return [
            'amount_cents' => $amountCents,
            'method' => PaymentMethod::BankTransfer->value,
            'payment_date' => '2027-02-20',
            'reference' => 'PAY-001',
            'notes' => null,
            'idempotency_key' => $key ?? (string) Str::uuid(),
        ];
    }
}
