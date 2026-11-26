<?php

namespace App\Domains\Invoicing\Jobs;

use App\Domains\Invoicing\Enums\DeliveryStatus;
use App\Domains\Invoicing\Enums\InvoiceDeliveryMethod;
use App\Domains\Invoicing\Models\InvoiceDelivery;
use App\Domains\Invoicing\Services\InvoiceActivityLogger;
use App\Domains\Invoicing\Services\InvoicingSettingsService;
use App\Mail\InvoiceIssuedMail;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;

class SendInvoiceEmailJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public int $backoff = 60;

    public function __construct(
        public int $deliveryId,
    ) {}

    public function handle(InvoiceActivityLogger $logger, InvoicingSettingsService $settings): void
    {
        $delivery = InvoiceDelivery::query()
            ->with(['invoice.document', 'processor'])
            ->find($this->deliveryId);

        if ($delivery === null || $delivery->status !== DeliveryStatus::Pending) {
            return;
        }

        $invoice = $delivery->invoice;

        if ($delivery->channel !== InvoiceDeliveryMethod::Email) {
            $this->failDelivery($delivery, $logger, 'Canal de distribution invalide.');

            return;
        }

        if (! $invoice->isDistributable()) {
            $this->failDelivery($delivery, $logger, 'La facture n’est plus distribuable.');

            return;
        }

        $delivery->update([
            'status' => DeliveryStatus::Pending,
            'processed_at' => null,
            'error' => null,
        ]);

        $document = $invoice->document;

        if (! $document?->isStored()) {
            $this->failDelivery($delivery, $logger, 'Le PDF archivé de la facture est introuvable.');

            return;
        }

        $disk = Storage::disk($document->disk);

        if (! $disk->exists($document->path)) {
            $this->failDelivery($delivery, $logger, 'Le fichier PDF archivé est introuvable.');

            return;
        }

        $pdf = $disk->get($document->path);

        if (! is_string($pdf) || $pdf === '') {
            throw new RuntimeException('Le PDF archivé n’a pas pu être lu.');
        }

        try {
            Mail::to($delivery->recipient)->send(new InvoiceIssuedMail($invoice, $pdf, $settings->replyToEmail()));
        } catch (Throwable $exception) {
            Log::warning('Invoice email delivery failed', [
                'invoice_delivery_id' => $delivery->id,
                'invoice_id' => $invoice->id,
                'batch_id' => $delivery->batch_id,
                'exception' => $exception,
            ]);

            $delivery->update([
                'error' => Str::limit($exception->getMessage(), 1000),
                'processed_at' => null,
            ]);

            throw $exception;
        }

        DB::transaction(function () use ($delivery, $invoice): void {
            $delivery->update([
                'status' => DeliveryStatus::Sent,
                'processed_at' => now(),
                'error' => null,
            ]);

            if ($invoice->sent_at === null) {
                $invoice->update(['sent_at' => now()]);
            }
        });

        $logger->record($invoice, 'invoice.email_sent', $delivery->processor, metadata: [
            'invoice_delivery_id' => $delivery->id,
            'batch_id' => $delivery->batch_id,
        ]);
    }

    public function failed(?Throwable $exception): void
    {
        $delivery = InvoiceDelivery::query()
            ->with(['invoice', 'processor'])
            ->find($this->deliveryId);

        if ($delivery === null || $delivery->status !== DeliveryStatus::Pending) {
            return;
        }

        if ($exception !== null) {
            $this->markFailed($delivery, $exception);
        }

        Log::error('Invoice email delivery permanently failed', [
            'invoice_delivery_id' => $delivery->id,
            'invoice_id' => $delivery->invoice_id,
            'batch_id' => $delivery->batch_id,
            'error' => $delivery->error,
            'exception' => $exception,
        ]);

        app(InvoiceActivityLogger::class)->record(
            $delivery->invoice,
            'invoice.email_failed',
            $delivery->processor,
            metadata: [
                'invoice_delivery_id' => $delivery->id,
                'batch_id' => $delivery->batch_id,
                'error' => $delivery->error,
            ],
        );
    }

    private function failDelivery(
        InvoiceDelivery $delivery,
        InvoiceActivityLogger $logger,
        string $message,
    ): void {
        $this->markFailed($delivery, $message);

        Log::warning('Invoice email delivery rejected', [
            'invoice_delivery_id' => $delivery->id,
            'invoice_id' => $delivery->invoice_id,
            'batch_id' => $delivery->batch_id,
            'error' => $delivery->error,
        ]);

        $logger->record($delivery->invoice, 'invoice.email_failed', $delivery->processor, metadata: [
            'invoice_delivery_id' => $delivery->id,
            'batch_id' => $delivery->batch_id,
            'error' => $delivery->error,
        ]);
    }

    private function markFailed(InvoiceDelivery $delivery, Throwable|string $exception): void
    {
        $message = $exception instanceof Throwable ? $exception->getMessage() : $exception;

        $delivery->update([
            'status' => DeliveryStatus::Failed,
            'processed_at' => now(),
            'error' => Str::limit($message, 1000),
        ]);
    }
}
