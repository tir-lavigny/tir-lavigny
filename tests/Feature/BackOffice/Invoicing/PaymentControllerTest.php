<?php

namespace Tests\Feature\BackOffice\Invoicing;

use App\Domains\Invoicing\Actions\Payments\AllocatePayment;
use App\Domains\Invoicing\Actions\Payments\RegisterPayment;
use App\Domains\Invoicing\Enums\InvoiceStatus;
use App\Domains\Invoicing\Enums\PaymentMethod;
use App\Domains\Invoicing\Models\Invoice;
use App\Domains\Invoicing\Models\InvoicePayment;
use App\Domains\Invoicing\Models\Payment;
use App\Domains\Invoicing\Services\InvoiceBalanceCalculator;
use App\Models\User;
use Carbon\Carbon;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Support\IssuesInvoices;
use Tests\TestCase;

class PaymentControllerTest extends TestCase
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

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_payment_index_lists_allocated_and_unallocated_amounts(): void
    {
        $payment = Payment::factory()->create(['amount_cents' => 10000, 'payment_date' => '2027-02-20']);
        $invoice = Invoice::factory()->create(['status' => 'issued']);
        InvoicePayment::factory()->create([
            'payment_id' => $payment->id,
            'invoice_id' => $invoice->id,
            'amount_cents' => 4000,
        ]);

        $this->get(route('bo.invoicing.payments.index'))
            ->assertInertia(fn (Assert $page) => $page
                ->component('backOffice/Invoicing/Payments/Index')
                ->has('payments.data', 1)
                ->where('payments.data.0.allocated_cents', 4000)
                ->where('payments.data.0.unallocated_cents', 6000)
            );
    }

    public function test_payment_create_page_renders(): void
    {
        $this->get(route('bo.invoicing.payments.create'))
            ->assertInertia(fn (Assert $page) => $page
                ->component('backOffice/Invoicing/Payments/Create')
                ->has('methods')
                ->has('idempotencyKey')
            );
    }

    public function test_registers_an_unallocated_payment(): void
    {
        $response = $this->post(route('bo.invoicing.payments.store'), $this->paymentData(10000));

        $payment = Payment::sole();

        $response->assertRedirect(route('bo.invoicing.payments.show', $payment));
        $this->assertSame(10000, $payment->amount_cents);
        $this->assertSame(PaymentMethod::BankTransfer, $payment->method);
        $this->assertSame(0, InvoicePayment::count());
    }

    public function test_registers_and_allocates_payment_from_invoice(): void
    {
        $invoice = $this->issueInvoice(actor: $this->actor);

        $response = $this->post(route('bo.invoicing.invoices.payments.store', $invoice), $this->paymentData(5000));

        $response->assertRedirect(route('bo.invoicing.invoices.show', $invoice));
        $payment = Payment::sole();
        $invoice->refresh();

        $this->assertSame(1, $payment->allocations()->count());
        $this->assertSame(InvoiceStatus::PartiallyPaid, $invoice->status);
        $this->assertSame(5000, app(InvoiceBalanceCalculator::class)->paidCents($invoice));
    }

    public function test_invoice_payment_over_outstanding_rolls_back(): void
    {
        $invoice = $this->issueInvoice(actor: $this->actor);

        $this->post(route('bo.invoicing.invoices.payments.store', $invoice), $this->paymentData(20000))
            ->assertSessionHasErrors('action');

        $this->assertDatabaseCount('payments', 0);
        $this->assertSame(InvoiceStatus::Issued, $invoice->fresh()->status);
    }

    public function test_payment_show_page_renders(): void
    {
        $payment = Payment::factory()->create(['amount_cents' => 10000, 'payment_date' => '2027-02-20']);

        $this->get(route('bo.invoicing.payments.show', $payment))
            ->assertInertia(fn (Assert $page) => $page
                ->component('backOffice/Invoicing/Payments/Show')
                ->where('allocatedCents', 0)
                ->where('availableCents', 10000)
                ->has('allocatableInvoices')
            );
    }

    public function test_allocates_payment_from_payment_page(): void
    {
        $payment = Payment::factory()->create(['amount_cents' => 10000, 'payment_date' => '2027-02-20']);
        $invoice = $this->issueInvoice(actor: $this->actor);

        $this->post(route('bo.invoicing.payments.allocations.store', $payment), [
            'invoice_id' => $invoice->id,
            'amount' => '50.00',
            'idempotency_key' => (string) Str::uuid(),
        ])->assertSessionHasNoErrors();

        $this->assertSame(InvoiceStatus::PartiallyPaid, $invoice->fresh()->status);
        $this->assertSame(5000, app(InvoiceBalanceCalculator::class)->paidCents($invoice->fresh()));
    }

    public function test_reverses_allocation_from_payment_page(): void
    {
        $invoice = $this->issueInvoice(actor: $this->actor);
        $payment = app(RegisterPayment::class)->execute($this->actionPaymentData(10000, 'PAY-REVERSE'), $this->actor);
        $allocation = app(AllocatePayment::class)->execute($payment, $invoice, 10000, $this->actor);

        $this->post(route('bo.invoicing.payments.allocations.reverse', $allocation), [
            'reason' => 'Mauvais montant',
        ])->assertSessionHasNoErrors();

        $this->assertNotNull($allocation->fresh()->reversed_at);
        $this->assertSame(InvoiceStatus::Issued, $invoice->fresh()->status);
    }

    public function test_updates_payment_metadata_and_amount_without_allocation(): void
    {
        $payment = Payment::factory()->create([
            'amount_cents' => 10000,
            'payment_date' => '2027-02-20',
            'method' => PaymentMethod::Cash,
        ]);

        $this->put(route('bo.invoicing.payments.update', $payment), [
            'amount' => '120.00',
            'payment_date' => '2027-02-21',
            'method' => PaymentMethod::Card->value,
            'reference' => 'Corrigé',
            'notes' => 'Correction',
        ])->assertRedirect(route('bo.invoicing.payments.show', $payment));

        $payment->refresh();
        $this->assertSame(12000, $payment->amount_cents);
        $this->assertSame(PaymentMethod::Card, $payment->method);
        $this->assertSame('Corrigé', $payment->reference);
    }

    public function test_voids_unallocated_payment(): void
    {
        $payment = Payment::factory()->create(['amount_cents' => 10000, 'payment_date' => '2027-02-20']);

        $this->post(route('bo.invoicing.payments.void', $payment), ['reason' => 'Erreur de saisie'])
            ->assertSessionHasNoErrors();

        $this->assertNotNull($payment->fresh()->voided_at);
        $this->assertSame('Erreur de saisie', $payment->fresh()->void_reason);
    }

    public function test_invoice_show_includes_payment_history_and_days_overdue(): void
    {
        Carbon::setTestNow('2027-03-01 12:00:00');
        $invoice = $this->issueInvoice(actor: $this->actor);
        $payment = app(RegisterPayment::class)->execute($this->actionPaymentData(5000, 'PAY-HISTORY'), $this->actor);
        app(AllocatePayment::class)->execute($payment, $invoice, 5000, $this->actor);

        $this->get(route('bo.invoicing.invoices.show', $invoice))
            ->assertInertia(fn (Assert $page) => $page
                ->component('backOffice/Invoicing/Invoices/Show')
                ->has('invoice.payment_allocations', 1)
                ->where('balance.days_overdue', 15)
            );
    }

    public function test_view_only_user_cannot_register_or_allocate_payment(): void
    {
        $invoice = $this->issueInvoice(actor: $this->actor);
        $payment = Payment::factory()->create();
        $viewer = User::factory()->create();
        $viewer->givePermissionTo(['access backoffice', 'view payments']);

        $this->actingAs($viewer)
            ->get(route('bo.invoicing.payments.index'))
            ->assertOk();

        $this->actingAs($viewer)
            ->get(route('bo.invoicing.payments.show', $payment))
            ->assertInertia(fn (Assert $page) => $page->has('allocatableInvoices', 0));

        $this->actingAs($viewer)
            ->get(route('bo.invoicing.payments.create'))
            ->assertForbidden();

        $this->actingAs($viewer)
            ->post(route('bo.invoicing.invoices.payments.store', $invoice), $this->paymentData(1000))
            ->assertForbidden();

        $this->actingAs($viewer)
            ->post(route('bo.invoicing.payments.allocations.store', $payment), [
                'invoice_id' => $invoice->id,
                'amount' => '10.00',
                'idempotency_key' => (string) Str::uuid(),
            ])
            ->assertForbidden();
    }

    /**
     * @return array<string, mixed>
     */
    private function paymentData(int $amountCents, ?string $key = null): array
    {
        return [
            'amount' => (string) ($amountCents / 100),
            'payment_date' => '2027-02-20',
            'method' => PaymentMethod::BankTransfer->value,
            'reference' => 'PAY-001',
            'notes' => null,
            'idempotency_key' => $key ?? (string) Str::uuid(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function actionPaymentData(int $amountCents, ?string $key = null): array
    {
        $data = $this->paymentData($amountCents, $key);
        $data['amount_cents'] = $amountCents;
        unset($data['amount']);

        return $data;
    }
}
