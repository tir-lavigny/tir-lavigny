<?php

namespace Tests\Feature\BackOffice\Invoicing;

use App\Domains\Invoicing\Actions\Reminders\SendInvoiceReminderEmail;
use App\Domains\Invoicing\Enums\DeliveryStatus;
use App\Domains\Invoicing\Enums\InvoiceDeliveryMethod;
use App\Domains\Invoicing\Enums\InvoiceStatus;
use App\Domains\Invoicing\Jobs\SendInvoiceReminderEmailJob;
use App\Domains\Invoicing\Models\InvoiceItem;
use App\Domains\Invoicing\Models\InvoiceReminder;
use App\Domains\Invoicing\Services\InvoiceActivityLogger;
use App\Domains\Invoicing\Services\InvoiceBalanceCalculator;
use App\Domains\Invoicing\Services\InvoiceReminderPdfGenerator;
use App\Domains\Invoicing\Services\InvoicingSettingsService;
use App\Mail\InvoiceReminderMail;
use App\Models\User;
use Carbon\Carbon;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;
use Inertia\Testing\AssertableInertia as Assert;
use RuntimeException;
use setasign\Fpdi\Fpdi;
use setasign\Fpdi\PdfParser\StreamReader;
use Tests\Support\IssuesInvoices;
use Tests\TestCase;

class InvoiceReminderControllerTest extends TestCase
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

    public function test_overdue_page_lists_only_overdue_invoices(): void
    {
        Carbon::setTestNow('2027-03-01 12:00:00');
        $overdue = $this->issueInvoice();
        $this->issueInvoice(invoiceOverrides: ['due_date' => '2027-04-01']);

        $this->get(route('bo.invoicing.reminders.overdue.index'))
            ->assertInertia(fn (Assert $page) => $page
                ->component('backOffice/Invoicing/Reminders/Overdue')
                ->has('invoices.data', 1)
                ->where('invoices.data.0.id', $overdue->id)
                ->where('invoices.data.0.days_overdue', 15)
                ->where('invoices.data.0.remaining_cents', 15000)
            );
    }

    public function test_invoice_index_can_filter_overdue_invoices(): void
    {
        Carbon::setTestNow('2027-03-01 12:00:00');
        $overdue = $this->issueInvoice();
        $this->issueInvoice(invoiceOverrides: ['due_date' => '2027-04-01']);

        $this->get(route('bo.invoicing.invoices.index', ['overdue' => 1]))
            ->assertInertia(fn (Assert $page) => $page
                ->component('backOffice/Invoicing/Invoices/Index')
                ->has('invoices.data', 1)
                ->where('invoices.data.0.id', $overdue->id)
                ->where('filters.overdue', true)
            );
    }

    public function test_email_reminder_queues_an_attempt_without_modifying_the_invoice(): void
    {
        Queue::fake();
        Carbon::setTestNow('2027-03-01 12:00:00');
        $invoice = $this->issueInvoice();
        $totalBefore = $invoice->total_cents;
        $itemsBefore = InvoiceItem::count();

        $this->post(route('bo.invoicing.invoices.reminders.email', $invoice))
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $reminder = InvoiceReminder::sole();

        $this->assertSame($invoice->id, $reminder->invoice_id);
        $this->assertSame(InvoiceDeliveryMethod::Email, $reminder->channel);
        $this->assertSame('alice@example.test', $reminder->recipient);
        $this->assertSame(15000, $reminder->outstanding_amount_cents);
        $this->assertSame(DeliveryStatus::Pending, $reminder->status);
        $this->assertNull($reminder->error);
        $this->assertSame($totalBefore, $invoice->fresh()->total_cents);
        $this->assertSame($itemsBefore, InvoiceItem::count());
        Queue::assertPushed(
            SendInvoiceReminderEmailJob::class,
            fn (SendInvoiceReminderEmailJob $job): bool => $job->reminderId === $reminder->id,
        );
    }

    public function test_duplicate_pending_reminder_is_rejected(): void
    {
        Queue::fake();
        Carbon::setTestNow('2027-03-01 12:00:00');
        $invoice = $this->issueInvoice();

        $this->post(route('bo.invoicing.invoices.reminders.email', $invoice))->assertSessionHasNoErrors();
        $this->post(route('bo.invoicing.invoices.reminders.email', $invoice))->assertSessionHasErrors('action');

        $this->assertSame(1, InvoiceReminder::count());
    }

    public function test_reminder_is_rejected_before_the_due_date(): void
    {
        Carbon::setTestNow('2027-01-20 12:00:00');
        $invoice = $this->issueInvoice();

        $this->post(route('bo.invoicing.invoices.reminders.email', $invoice))
            ->assertForbidden();

        $this->assertDatabaseCount('invoice_reminders', 0);
    }

    public function test_email_reminder_job_sends_pdf_and_tracks_success(): void
    {
        Queue::fake();
        Mail::fake();
        Carbon::setTestNow('2027-03-01 12:00:00');
        $invoice = $this->issueInvoice();
        $reminder = app(SendInvoiceReminderEmail::class)->execute($invoice, User::firstOrFail());

        $this->handleJob($reminder);

        $reminder->refresh();

        $this->assertSame(DeliveryStatus::Sent, $reminder->status);
        $this->assertNotNull($reminder->processed_at);
        $this->assertNull($reminder->error);
        Mail::assertSent(InvoiceReminderMail::class, function (InvoiceReminderMail $mail): bool {
            return $mail->hasTo('alice@example.test')
                && $mail->reminder->outstanding_amount_cents === 15000
                && str_starts_with($mail->pdfContent, '%PDF');
        });
        $this->assertDatabaseHas('invoice_activity_logs', [
            'invoice_id' => $invoice->id,
            'action' => 'invoice.reminder_sent',
        ]);
    }

    public function test_email_reminder_job_fails_when_invoice_became_paid(): void
    {
        Queue::fake();
        Mail::fake();
        Carbon::setTestNow('2027-03-01 12:00:00');
        $invoice = $this->issueInvoice();
        $reminder = app(SendInvoiceReminderEmail::class)->execute($invoice, User::firstOrFail());
        $invoice->update(['status' => InvoiceStatus::Paid]);
        $this->assertSame(InvoiceStatus::Paid, $invoice->fresh()->status);

        $this->handleJob($reminder);

        $reminder->refresh();

        $this->assertSame(DeliveryStatus::Failed, $reminder->status);
        $this->assertNotNull($reminder->processed_at);
        $this->assertStringContainsString('solde impayé', (string) $reminder->error);
        Mail::assertNothingSent();
        $this->assertDatabaseHas('invoice_activity_logs', [
            'invoice_id' => $invoice->id,
            'action' => 'invoice.reminder_failed',
        ]);
    }

    public function test_reminder_email_job_marks_failed_after_smtp_retries_are_exhausted(): void
    {
        Queue::fake();
        Carbon::setTestNow('2027-03-01 12:00:00');
        $invoice = $this->issueInvoice();
        $reminder = app(SendInvoiceReminderEmail::class)->execute($invoice, User::firstOrFail());
        Mail::shouldReceive('to')->once()->andThrow(new RuntimeException('SMTP indisponible'));
        $job = new SendInvoiceReminderEmailJob($reminder->id);

        try {
            $job->handle(
                app(InvoiceReminderPdfGenerator::class),
                app(InvoiceActivityLogger::class),
                app(InvoiceBalanceCalculator::class),
                app(InvoicingSettingsService::class),
            );
            $this->fail('The reminder job should have rethrown the mail exception.');
        } catch (RuntimeException $exception) {
            $this->assertSame('SMTP indisponible', $exception->getMessage());
        }

        $reminder->refresh();
        $this->assertSame(DeliveryStatus::Pending, $reminder->status);
        $this->assertNull($reminder->processed_at);
        $this->assertSame('SMTP indisponible', $reminder->error);

        $job->failed(new RuntimeException('SMTP indisponible'));

        $reminder->refresh();
        $this->assertSame(DeliveryStatus::Failed, $reminder->status);
        $this->assertNotNull($reminder->processed_at);
        $this->assertSame('SMTP indisponible', $reminder->error);
        $this->assertDatabaseHas('invoice_activity_logs', [
            'invoice_id' => $invoice->id,
            'action' => 'invoice.reminder_failed',
        ]);
    }

    public function test_postal_reminder_generates_a_printable_pdf_and_tracks_history(): void
    {
        Carbon::setTestNow('2027-03-01 12:00:00');
        $invoice = $this->issueInvoice(InvoiceDeliveryMethod::Postal);
        $totalBefore = $invoice->total_cents;

        $response = $this->post(route('bo.invoicing.invoices.reminders.print', $invoice), [], ['Accept' => 'application/json']);

        $response->assertOk()->assertHeader('Content-Type', 'application/pdf');
        $pdf = $response->streamedContent();
        $this->assertStringStartsWith('%PDF', $pdf);

        $reader = new Fpdi;
        $this->assertSame(2, $reader->setSourceFile(StreamReader::createByString($pdf)));

        $reminder = InvoiceReminder::sole();
        $this->assertSame(InvoiceDeliveryMethod::Postal, $reminder->channel);
        $this->assertSame(DeliveryStatus::Printed, $reminder->status);
        $this->assertStringContainsString('Alice Martin', $reminder->recipient);
        $this->assertSame($totalBefore, $invoice->fresh()->total_cents);
        $this->assertDatabaseHas('invoice_activity_logs', [
            'invoice_id' => $invoice->id,
            'action' => 'invoice.reminder_printed',
        ]);
    }

    public function test_postal_reminder_marks_failed_when_pdf_generation_fails(): void
    {
        Carbon::setTestNow('2027-03-01 12:00:00');
        $invoice = $this->issueInvoice(InvoiceDeliveryMethod::Postal);
        $this->mock(InvoiceReminderPdfGenerator::class, function ($mock): void {
            $mock->shouldReceive('generate')->once()->andThrow(new RuntimeException('PDF indisponible'));
        });

        $response = $this->post(route('bo.invoicing.invoices.reminders.print', $invoice), [], ['Accept' => 'application/json']);

        $response->assertStatus(422)->assertJson([
            'message' => 'Le rappel postal n’a pas pu être généré : PDF indisponible',
        ]);

        $reminder = InvoiceReminder::sole();
        $this->assertSame(DeliveryStatus::Failed, $reminder->status);
        $this->assertNotNull($reminder->processed_at);
        $this->assertSame('PDF indisponible', $reminder->error);
        $this->assertDatabaseHas('invoice_activity_logs', [
            'invoice_id' => $invoice->id,
            'action' => 'invoice.reminder_failed',
        ]);
    }

    public function test_invoice_show_includes_reminder_history(): void
    {
        $invoice = $this->issueInvoice();
        InvoiceReminder::factory()->for($invoice)->create([
            'channel' => InvoiceDeliveryMethod::Email,
            'recipient' => 'alice@example.test',
            'outstanding_amount_cents' => 15000,
            'status' => DeliveryStatus::Sent,
            'processed_at' => now(),
            'processed_by' => User::firstOrFail()->id,
        ]);

        $this->get(route('bo.invoicing.invoices.show', $invoice))
            ->assertInertia(fn (Assert $page) => $page
                ->component('backOffice/Invoicing/Invoices/Show')
                ->has('invoice.reminders', 1)
                ->where('invoice.reminders.0.status', DeliveryStatus::Sent->value)
            );
    }

    public function test_view_only_user_cannot_send_or_print_reminders(): void
    {
        Carbon::setTestNow('2027-03-01 12:00:00');
        $invoice = $this->issueInvoice();
        $viewer = User::factory()->create();
        $viewer->givePermissionTo(['access backoffice', 'view invoices']);

        $this->actingAs($viewer)
            ->post(route('bo.invoicing.invoices.reminders.email', $invoice))
            ->assertForbidden();

        $this->actingAs($viewer)
            ->post(route('bo.invoicing.invoices.reminders.print', $invoice))
            ->assertForbidden();
    }

    public function test_reminder_manager_can_send_a_reminder(): void
    {
        Queue::fake();
        Carbon::setTestNow('2027-03-01 12:00:00');
        $invoice = $this->issueInvoice();
        $manager = User::factory()->create();
        $manager->givePermissionTo(['access backoffice', 'view invoices', 'manage invoice reminders']);

        $this->actingAs($manager)
            ->post(route('bo.invoicing.invoices.reminders.email', $invoice))
            ->assertSessionHasNoErrors();

        $this->assertSame(1, InvoiceReminder::count());
    }

    private function handleJob(InvoiceReminder $reminder): void
    {
        (new SendInvoiceReminderEmailJob($reminder->id))->handle(
            app(InvoiceReminderPdfGenerator::class),
            app(InvoiceActivityLogger::class),
            app(InvoiceBalanceCalculator::class),
            app(InvoicingSettingsService::class),
        );
    }
}
