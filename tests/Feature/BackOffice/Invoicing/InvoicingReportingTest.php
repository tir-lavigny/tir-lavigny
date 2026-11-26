<?php

namespace Tests\Feature\BackOffice\Invoicing;

use App\Domains\Invoicing\Actions\Payments\AllocatePayment;
use App\Domains\Invoicing\Actions\Payments\RegisterPayment;
use App\Domains\Invoicing\Enums\DeliveryStatus;
use App\Domains\Invoicing\Enums\InvoiceDeliveryMethod;
use App\Domains\Invoicing\Models\InvoiceReminder;
use App\Models\User;
use Carbon\Carbon;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Support\IssuesInvoices;
use Tests\TestCase;

class InvoicingReportingTest extends TestCase
{
    use IssuesInvoices;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_dashboard_aggregates_invoicing_indicators(): void
    {
        Carbon::setTestNow('2027-03-01 12:00:00');
        $actor = $this->invoicingAdmin();
        $invoice = $this->issueInvoice(actor: $actor);
        $payment = app(RegisterPayment::class)->execute([
            'amount_cents' => 5000,
            'method' => 'bank_transfer',
            'payment_date' => '2027-03-01',
            'reference' => 'PAY-DASHBOARD',
            'notes' => null,
            'idempotency_key' => (string) Str::uuid(),
        ], $actor);
        app(AllocatePayment::class)->execute($payment, $invoice, 5000, $actor);

        InvoiceReminder::factory()->for($invoice)->create([
            'channel' => InvoiceDeliveryMethod::Email,
            'recipient' => 'alice@example.test',
            'outstanding_amount_cents' => 10000,
            'status' => DeliveryStatus::Sent,
            'processed_at' => now(),
            'processed_by' => $actor->id,
        ]);

        $this->get(route('bo.invoicing.dashboard', ['year' => 2027]))
            ->assertInertia(fn (Assert $page) => $page
                ->component('backOffice/Invoicing/Dashboard')
                ->where('summary.issued_count', 1)
                ->where('summary.issued_total_cents', 15000)
                ->where('summary.paid_cents', 5000)
                ->where('summary.credited_cents', 0)
                ->where('summary.outstanding_cents', 10000)
                ->where('summary.overdue_count', 1)
                ->where('summary.overdue_cents', 10000)
                ->where('summary.awaiting_payment_count', 1)
                ->where('summary.awaiting_email_count', 1)
                ->where('summary.awaiting_postal_count', 0)
                ->where('summary.reminders_count', 1)
                ->where('summary.status_counts.2.value', 'partially_paid')
                ->where('summary.status_counts.2.count', 1)
                ->where('invoicingConfigured', true)
            );
    }

    public function test_dashboard_counts_postal_queue_separately(): void
    {
        Carbon::setTestNow('2027-03-01 12:00:00');
        $this->issueInvoice(InvoiceDeliveryMethod::Postal);

        $this->get(route('bo.invoicing.dashboard', ['year' => 2027]))
            ->assertInertia(fn (Assert $page) => $page
                ->where('summary.awaiting_email_count', 0)
                ->where('summary.awaiting_postal_count', 1)
            );
    }

    public function test_user_without_invoicing_permission_cannot_view_dashboard(): void
    {
        $user = User::factory()->create();
        $user->givePermissionTo('access backoffice');

        $this->actingAs($user)
            ->get(route('bo.invoicing.dashboard'))
            ->assertForbidden();
    }
}
