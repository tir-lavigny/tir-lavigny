<?php

namespace App\Domains\Invoicing\Jobs;

use App\Domains\Invoicing\Enums\DeliveryStatus;
use App\Domains\Invoicing\Enums\InvoiceDeliveryMethod;
use App\Domains\Invoicing\Enums\InvoiceStatus;
use App\Domains\Invoicing\Models\InvoiceReminder;
use App\Domains\Invoicing\Services\InvoiceActivityLogger;
use App\Domains\Invoicing\Services\InvoiceBalanceCalculator;
use App\Domains\Invoicing\Services\InvoiceReminderPdfGenerator;
use App\Domains\Invoicing\Services\InvoicingSettingsService;
use App\Mail\InvoiceReminderMail;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Throwable;

class SendInvoiceReminderEmailJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public int $backoff = 60;

    public function __construct(
        public int $reminderId,
    ) {}

    public function handle(
        InvoiceReminderPdfGenerator $pdfGenerator,
        InvoiceActivityLogger $logger,
        InvoiceBalanceCalculator $balances,
        InvoicingSettingsService $settings,
    ): void {
        $reminder = InvoiceReminder::query()
            ->with('invoice')
            ->find($this->reminderId);

        if ($reminder === null || $reminder->status !== DeliveryStatus::Pending) {
            return;
        }

        if ($reminder->channel !== InvoiceDeliveryMethod::Email) {
            $this->failReminder($reminder, $logger, 'Canal de rappel invalide.');

            return;
        }

        $invoice = $reminder->invoice;

        if (! $invoice->isDistributable()) {
            $this->failReminder($reminder, $logger, 'La facture n’est plus distribuable.');

            return;
        }

        if ($balances->outstandingCents($invoice) <= 0 || $invoice->status === InvoiceStatus::Paid) {
            $this->failReminder($reminder, $logger, 'La facture ne présente plus de solde impayé.');

            return;
        }

        try {
            $pdf = $pdfGenerator->generate($reminder);
            Mail::to($reminder->recipient)->send(new InvoiceReminderMail(
                $invoice,
                $reminder,
                $pdf,
                $settings->replyToEmail(),
            ));
        } catch (Throwable $exception) {
            $reminder->update([
                'error' => Str::limit($exception->getMessage(), 1000),
                'processed_at' => null,
            ]);

            throw $exception;
        }

        DB::transaction(function () use ($reminder): void {
            $reminder->update([
                'status' => DeliveryStatus::Sent,
                'processed_at' => now(),
                'error' => null,
            ]);
        });

        $logger->record($reminder->invoice, 'invoice.reminder_sent', $reminder->processor, metadata: [
            'invoice_reminder_id' => $reminder->id,
            'channel' => InvoiceDeliveryMethod::Email->value,
            'outstanding_amount_cents' => $reminder->outstanding_amount_cents,
        ]);
    }

    public function failed(?Throwable $exception): void
    {
        $reminder = InvoiceReminder::query()
            ->with('invoice')
            ->find($this->reminderId);

        if ($reminder === null || $reminder->status !== DeliveryStatus::Pending) {
            return;
        }

        if ($exception !== null) {
            $this->markFailed($reminder, $exception);
        }

        app(InvoiceActivityLogger::class)->record($reminder->invoice, 'invoice.reminder_failed', $reminder->processor, metadata: [
            'invoice_reminder_id' => $reminder->id,
            'channel' => InvoiceDeliveryMethod::Email->value,
            'error' => $reminder->error,
        ]);
    }

    private function failReminder(
        InvoiceReminder $reminder,
        InvoiceActivityLogger $logger,
        string $message,
    ): void {
        $this->markFailed($reminder, $message);

        $logger->record($reminder->invoice, 'invoice.reminder_failed', $reminder->processor, metadata: [
            'invoice_reminder_id' => $reminder->id,
            'channel' => InvoiceDeliveryMethod::Email->value,
            'error' => $reminder->error,
        ]);
    }

    private function markFailed(InvoiceReminder $reminder, Throwable|string $exception): void
    {
        $message = $exception instanceof Throwable ? $exception->getMessage() : $exception;

        $reminder->update([
            'status' => DeliveryStatus::Failed,
            'processed_at' => now(),
            'error' => Str::limit($message, 1000),
        ]);
    }
}
