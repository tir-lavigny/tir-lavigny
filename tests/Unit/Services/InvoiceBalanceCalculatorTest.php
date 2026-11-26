<?php

namespace Tests\Unit\Services;

use App\Domains\Invoicing\Enums\InvoiceStatus;
use App\Domains\Invoicing\Models\CreditNoteApplication;
use App\Domains\Invoicing\Models\CreditNoteApplicationReversal;
use App\Domains\Invoicing\Models\Invoice;
use App\Domains\Invoicing\Models\InvoicePayment;
use App\Domains\Invoicing\Services\InvoiceBalanceCalculator;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class InvoiceBalanceCalculatorTest extends TestCase
{
    use RefreshDatabase;

    public function test_calculates_balances_from_payments_credits_and_reversals(): void
    {
        $invoice = Invoice::factory()->create([
            'status' => InvoiceStatus::PartiallyPaid,
            'total_cents' => 10000,
        ]);

        InvoicePayment::factory()->for($invoice)->create(['amount_cents' => 3000]);
        InvoicePayment::factory()->for($invoice)->create([
            'amount_cents' => 2000,
            'reversed_at' => now(),
        ]);

        $application = CreditNoteApplication::factory()->for($invoice)->create(['amount_cents' => 1000]);
        CreditNoteApplicationReversal::factory()->for($application, 'application')->create(['amount_cents' => 500]);

        $calculator = app(InvoiceBalanceCalculator::class);

        $this->assertSame(3000, $calculator->paidCents($invoice));
        $this->assertSame(500, $calculator->creditedCents($invoice));
        $this->assertSame(6500, $calculator->outstandingCents($invoice));
    }

    public function test_applies_balance_attributes_and_clamps_outstanding_to_zero(): void
    {
        $invoice = Invoice::factory()->create([
            'status' => InvoiceStatus::Issued,
            'total_cents' => 1000,
            'due_date' => '2027-02-14',
        ]);

        InvoicePayment::factory()->for($invoice)->create(['amount_cents' => 1500]);

        $calculator = app(InvoiceBalanceCalculator::class);
        $invoice = $calculator->applyBalanceAttributes(
            $calculator->withBalanceSums(Invoice::query())->findOrFail($invoice->id),
        );

        $this->assertSame(1500, $invoice->getAttribute('paid_cents'));
        $this->assertSame(0, $invoice->getAttribute('credited_cents'));
        $this->assertSame(0, $invoice->getAttribute('remaining_cents'));
        $this->assertSame(0, $invoice->getAttribute('days_overdue'));
    }

    public function test_days_overdue_uses_the_due_date_and_remaining_balance(): void
    {
        $calculator = app(InvoiceBalanceCalculator::class);
        $invoice = Invoice::factory()->create([
            'status' => InvoiceStatus::Issued,
            'total_cents' => 10000,
            'due_date' => '2027-02-14',
        ]);

        $this->assertSame(0, $calculator->daysOverdue($invoice, Carbon::parse('2027-02-14')));
        $this->assertSame(1, $calculator->daysOverdue($invoice, Carbon::parse('2027-02-15')));
        $this->assertSame(11, $calculator->daysOverdue($invoice, Carbon::parse('2027-02-25')));

        InvoicePayment::factory()->for($invoice)->create(['amount_cents' => 10000]);

        $this->assertSame(0, $calculator->daysOverdue($invoice->fresh(), Carbon::parse('2027-02-25')));
    }

    public function test_days_overdue_is_zero_for_draft_and_cancelled_invoices(): void
    {
        $calculator = app(InvoiceBalanceCalculator::class);
        $draft = Invoice::factory()->create([
            'status' => InvoiceStatus::Draft,
            'total_cents' => 10000,
            'due_date' => '2027-02-14',
        ]);
        $cancelled = Invoice::factory()->create([
            'status' => InvoiceStatus::Cancelled,
            'total_cents' => 10000,
            'due_date' => '2027-02-14',
        ]);

        $this->assertSame(0, $calculator->daysOverdue($draft, Carbon::parse('2027-02-25')));
        $this->assertSame(0, $calculator->daysOverdue($cancelled, Carbon::parse('2027-02-25')));
    }

    public function test_refresh_status_derives_issued_partially_paid_and_paid(): void
    {
        $calculator = app(InvoiceBalanceCalculator::class);
        $invoice = Invoice::factory()->create([
            'status' => InvoiceStatus::Issued,
            'total_cents' => 10000,
        ]);

        $this->assertSame(InvoiceStatus::Issued, $calculator->refreshStatus($invoice)->status);

        InvoicePayment::factory()->for($invoice)->create(['amount_cents' => 4000]);
        $this->assertSame(InvoiceStatus::PartiallyPaid, $calculator->refreshStatus($invoice)->status);

        $application = CreditNoteApplication::factory()->for($invoice)->create(['amount_cents' => 6000]);
        $this->assertSame(InvoiceStatus::Paid, $calculator->refreshStatus($invoice)->status);

        CreditNoteApplicationReversal::factory()->for($application, 'application')->create(['amount_cents' => 6000]);
        $this->assertSame(InvoiceStatus::PartiallyPaid, $calculator->refreshStatus($invoice)->status);
    }
}
