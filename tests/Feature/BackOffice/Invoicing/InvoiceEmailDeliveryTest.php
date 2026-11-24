<?php

namespace Tests\Feature\BackOffice\Invoicing;

use App\Domains\Invoicing\Actions\Deliveries\SendInvoiceEmail;
use App\Domains\Invoicing\Enums\DeliveryStatus;
use App\Domains\Invoicing\Enums\FinancialDocumentStatus;
use App\Domains\Invoicing\Enums\InvoiceDeliveryMethod;
use App\Domains\Invoicing\Jobs\SendInvoiceEmailJob;
use App\Domains\Invoicing\Models\InvoiceDelivery;
use App\Domains\Invoicing\Models\InvoicingSettings;
use App\Domains\Invoicing\Services\InvoiceActivityLogger;
use App\Domains\Invoicing\Services\InvoicingSettingsService;
use App\Mail\InvoiceIssuedMail;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;
use RuntimeException;
use Tests\Support\IssuesInvoices;
use Tests\TestCase;

class InvoiceEmailDeliveryTest extends TestCase
{
    use IssuesInvoices;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);
    }

    public function test_queues_an_email_delivery_attempt(): void
    {
        Queue::fake();
        $invoice = $this->issueInvoice();

        $response = $this->post(route('bo.invoicing.invoices.email', $invoice));

        $response->assertRedirect();
        $delivery = InvoiceDelivery::sole();
        $this->assertSame($invoice->id, $delivery->invoice_id);
        $this->assertSame(InvoiceDeliveryMethod::Email, $delivery->channel);
        $this->assertSame('alice@example.test', $delivery->recipient);
        $this->assertSame(DeliveryStatus::Pending, $delivery->status);
        $this->assertNotNull($delivery->batch_id);
        $this->assertNull($delivery->processed_at);
        Queue::assertPushed(SendInvoiceEmailJob::class, fn (SendInvoiceEmailJob $job): bool => $job->deliveryId === $delivery->id);
    }

    public function test_sends_the_archived_pdf_and_tracks_success(): void
    {
        Queue::fake();
        Mail::fake();
        $invoice = $this->issueInvoice();
        $delivery = app(SendInvoiceEmail::class)->execute($invoice, User::firstOrFail());
        Storage::disk($invoice->document->disk)->put($invoice->document->path, 'archived-invoice');

        $this->handleJob($delivery);

        $delivery->refresh();
        $invoice->refresh();

        $this->assertSame(DeliveryStatus::Sent, $delivery->status);
        $this->assertNotNull($delivery->processed_at);
        $this->assertNull($delivery->error);
        $this->assertNotNull($invoice->sent_at);
        Mail::assertSent(InvoiceIssuedMail::class, fn (InvoiceIssuedMail $mail): bool => $mail->hasTo('alice@example.test'));
        $this->assertDatabaseHas('invoice_activity_logs', [
            'invoice_id' => $invoice->id,
            'action' => 'invoice.email_sent',
        ]);
    }

    public function test_email_uses_the_configured_reply_to_address(): void
    {
        Queue::fake();
        Mail::fake();
        InvoicingSettings::query()->update(['reply_to_email' => 'tresorier@example.test']);
        $invoice = $this->issueInvoice();
        $delivery = app(SendInvoiceEmail::class)->execute($invoice, User::firstOrFail());

        $this->handleJob($delivery);

        Mail::assertSent(
            InvoiceIssuedMail::class,
            fn (InvoiceIssuedMail $mail): bool => $mail->hasReplyTo('tresorier@example.test'),
        );
    }

    public function test_marks_email_as_failed_when_archived_pdf_is_missing(): void
    {
        Queue::fake();
        Mail::fake();
        $invoice = $this->issueInvoice();
        $delivery = app(SendInvoiceEmail::class)->execute($invoice, User::firstOrFail());
        Storage::disk($invoice->document->disk)->delete($invoice->document->path);

        $this->handleJob($delivery);

        $delivery->refresh();

        $this->assertSame(DeliveryStatus::Failed, $delivery->status);
        $this->assertNotNull($delivery->processed_at);
        $this->assertStringContainsString('PDF archivé', (string) $delivery->error);
        $this->assertDatabaseHas('invoice_activity_logs', [
            'invoice_id' => $invoice->id,
            'action' => 'invoice.email_failed',
        ]);
        Mail::assertNothingSent();
    }

    public function test_marks_email_as_failed_after_smtp_retries_are_exhausted(): void
    {
        Queue::fake();
        $invoice = $this->issueInvoice();
        $delivery = app(SendInvoiceEmail::class)->execute($invoice, User::firstOrFail());
        Mail::shouldReceive('to')->once()->andThrow(new RuntimeException('SMTP indisponible'));
        $job = new SendInvoiceEmailJob($delivery->id);

        try {
            $job->handle(app(InvoiceActivityLogger::class), app(InvoicingSettingsService::class));
            $this->fail('The job should have rethrown the mail exception.');
        } catch (RuntimeException $exception) {
            $this->assertSame('SMTP indisponible', $exception->getMessage());
        }

        $delivery->refresh();
        $this->assertSame(DeliveryStatus::Pending, $delivery->status);
        $this->assertSame('SMTP indisponible', $delivery->error);

        $job->failed(new RuntimeException('SMTP indisponible'));

        $delivery->refresh();
        $this->assertSame(DeliveryStatus::Failed, $delivery->status);
        $this->assertNotNull($delivery->processed_at);
        $this->assertSame('SMTP indisponible', $delivery->error);
    }

    public function test_a_new_attempt_can_succeed_after_a_missing_document_failure(): void
    {
        Queue::fake();
        Mail::fake();
        $invoice = $this->issueInvoice();
        $first = app(SendInvoiceEmail::class)->execute($invoice, User::firstOrFail());
        $path = $invoice->document->path;
        Storage::disk($invoice->document->disk)->delete($path);

        $this->handleJob($first);
        $this->assertSame(DeliveryStatus::Failed, $first->fresh()->status);

        Storage::disk($invoice->document->disk)->put($path, '%PDF-1.4 retried');

        $second = app(SendInvoiceEmail::class)->execute($invoice->fresh(), User::firstOrFail());
        $this->handleJob($second);

        $this->assertSame(DeliveryStatus::Failed, $first->fresh()->status);
        $this->assertSame(DeliveryStatus::Sent, $second->fresh()->status);
        $this->assertNull($second->fresh()->error);
        $this->assertSame(2, InvoiceDelivery::count());
    }

    public function test_resending_creates_a_new_delivery_attempt(): void
    {
        Queue::fake();
        Mail::fake();
        $invoice = $this->issueInvoice();
        $first = app(SendInvoiceEmail::class)->execute($invoice, User::firstOrFail());

        $this->handleJob($first);

        $second = app(SendInvoiceEmail::class)->execute($invoice->fresh(), User::firstOrFail());

        $this->assertNotSame($first->id, $second->id);
        $this->assertSame(2, InvoiceDelivery::count());
        $this->assertSame(DeliveryStatus::Sent, $first->fresh()->status);
        $this->assertSame(DeliveryStatus::Pending, $second->status);
    }

    public function test_blocks_a_duplicate_email_while_an_attempt_is_pending(): void
    {
        Queue::fake();
        $invoice = $this->issueInvoice();
        InvoiceDelivery::factory()->for($invoice)->create([
            'channel' => InvoiceDeliveryMethod::Email,
            'recipient' => 'alice@example.test',
            'status' => DeliveryStatus::Pending,
            'created_at' => now(),
        ]);

        $response = $this->post(route('bo.invoicing.invoices.email', $invoice));

        $response->assertSessionHasErrors('action');
        $this->assertDatabaseCount('invoice_deliveries', 1);
    }

    public function test_allows_retry_after_a_pending_attempt_is_stale(): void
    {
        Queue::fake();
        $invoice = $this->issueInvoice();
        $stale = InvoiceDelivery::factory()->for($invoice)->create([
            'channel' => InvoiceDeliveryMethod::Email,
            'recipient' => 'alice@example.test',
            'status' => DeliveryStatus::Pending,
            'created_at' => now()->subMinutes(20),
        ]);

        $this->post(route('bo.invoicing.invoices.email', $invoice))->assertSessionHasNoErrors();

        $this->assertDatabaseCount('invoice_deliveries', 2);
        $this->assertSame(DeliveryStatus::Failed, $stale->fresh()->status);
        $this->assertSame('Tentative d’envoi expirée.', $stale->fresh()->error);
    }

    public function test_uses_invoice_email_snapshot_after_customer_email_changes(): void
    {
        Queue::fake();
        $invoice = $this->issueInvoice();
        $invoice->billingCustomer->update(['email' => 'new@example.test']);

        $delivery = app(SendInvoiceEmail::class)->execute($invoice->fresh(), User::firstOrFail());

        $this->assertSame('alice@example.test', $delivery->recipient);
    }

    public function test_rejects_email_when_document_is_not_stored(): void
    {
        Queue::fake();
        $invoice = $this->issueInvoice();
        $invoice->document->update(['status' => FinancialDocumentStatus::Failed]);

        $response = $this->post(route('bo.invoicing.invoices.email', $invoice));

        $response->assertSessionHasErrors('action');
        $this->assertDatabaseCount('invoice_deliveries', 0);
    }

    public function test_bulk_send_queues_each_selected_invoice(): void
    {
        Queue::fake();
        $first = $this->issueInvoice();
        $second = $this->issueInvoice();

        $response = $this->post(route('bo.invoicing.deliveries.email.send'), [
            'invoice_ids' => [$first->id, $second->id],
        ]);

        $response->assertRedirect();
        $this->assertSame(2, InvoiceDelivery::count());
        $this->assertSame(1, InvoiceDelivery::query()->pluck('batch_id')->unique()->count());
        Queue::assertPushed(SendInvoiceEmailJob::class, 2);
    }

    public function test_email_queue_lists_only_awaiting_invoices(): void
    {
        $awaiting = $this->issueInvoice();
        $sent = $this->issueInvoice();
        InvoiceDelivery::factory()->for($sent)->create([
            'channel' => InvoiceDeliveryMethod::Email,
            'recipient' => $sent->recipient_email,
            'status' => DeliveryStatus::Sent,
            'processed_at' => now(),
            'processed_by' => User::firstOrFail()->id,
        ]);

        $this->get(route('bo.invoicing.deliveries.email.index'))
            ->assertInertia(fn (Assert $page) => $page
                ->component('backOffice/Invoicing/Deliveries/Email')
                ->has('invoices.data', 1)
                ->where('invoices.data.0.id', $awaiting->id)
            );
    }

    public function test_email_queue_exposes_the_last_failure(): void
    {
        $invoice = $this->issueInvoice();
        InvoiceDelivery::factory()->for($invoice)->create([
            'channel' => InvoiceDeliveryMethod::Email,
            'recipient' => 'alice@example.test',
            'status' => DeliveryStatus::Failed,
            'processed_at' => now(),
            'processed_by' => User::firstOrFail()->id,
            'error' => 'SMTP indisponible',
        ]);

        $this->get(route('bo.invoicing.deliveries.email.index'))
            ->assertInertia(fn (Assert $page) => $page
                ->component('backOffice/Invoicing/Deliveries/Email')
                ->has('invoices.data', 1)
                ->where('invoices.data.0.latest_delivery.status', 'failed')
                ->where('invoices.data.0.latest_delivery.error', 'SMTP indisponible')
            );
    }

    public function test_invoice_show_includes_delivery_history(): void
    {
        $invoice = $this->issueInvoice();
        InvoiceDelivery::factory()->for($invoice)->create([
            'channel' => InvoiceDeliveryMethod::Email,
            'recipient' => 'alice@example.test',
            'status' => DeliveryStatus::Failed,
            'processed_at' => now(),
            'processed_by' => User::firstOrFail()->id,
            'error' => 'SMTP indisponible',
        ]);

        $this->get(route('bo.invoicing.invoices.show', $invoice))
            ->assertInertia(fn (Assert $page) => $page
                ->component('backOffice/Invoicing/Invoices/Show')
                ->has('invoice.deliveries', 1)
                ->where('invoice.deliveries.0.status', 'failed')
                ->where('invoice.deliveries.0.error', 'SMTP indisponible')
            );
    }

    public function test_mailable_contains_invoice_data_and_pdf_attachment(): void
    {
        $invoice = $this->issueInvoice();
        $pdf = '%PDF-1.4 test invoice';

        (new InvoiceIssuedMail($invoice, $pdf))
            ->assertSeeInHtml('Instrument Sans')
            ->assertSeeInHtml('Montant total')
            ->assertSeeInHtml((string) $invoice->invoice_number)
            ->assertSeeInHtml('150.00')
            ->assertSeeInHtml('14.02.2027')
            ->assertSeeInText((string) $invoice->payment_reference)
            ->assertHasAttachedData($pdf, "facture-{$invoice->invoice_number}.pdf", ['mime' => 'application/pdf']);
    }

    public function test_view_only_user_cannot_send_invoice_email(): void
    {
        $viewer = User::factory()->create();
        $viewer->givePermissionTo(['access backoffice', 'view invoices']);
        $invoice = $this->issueInvoice();
        Queue::fake();

        $this->actingAs($viewer)
            ->post(route('bo.invoicing.invoices.email', $invoice))
            ->assertForbidden();
    }

    private function handleJob(InvoiceDelivery $delivery): void
    {
        (new SendInvoiceEmailJob($delivery->id))->handle(
            app(InvoiceActivityLogger::class),
            app(InvoicingSettingsService::class),
        );
    }
}
