<?php

namespace App\Domains\Invoicing\Actions\Reminders;

use App\Domains\Invoicing\Enums\DeliveryStatus;
use App\Domains\Invoicing\Enums\InvoiceDeliveryMethod;
use App\Domains\Invoicing\Exceptions\InvoiceReminderException;
use App\Domains\Invoicing\Models\Invoice;
use App\Domains\Invoicing\Models\InvoiceReminder;
use App\Domains\Invoicing\Services\InvoiceActivityLogger;
use App\Domains\Invoicing\Services\InvoiceReminderPdfGenerator;
use App\Models\User;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Throwable;

class PrintInvoiceReminder
{
    public function __construct(
        private readonly CreateInvoiceReminder $createReminder,
        private readonly InvoiceReminderPdfGenerator $pdfGenerator,
        private readonly InvoiceActivityLogger $activityLogger,
    ) {}

    /**
     * @return array{pdf: string, reminder: InvoiceReminder}
     */
    public function execute(Invoice $invoice, User $actor): array
    {
        $reminder = $this->createReminder->execute($invoice, InvoiceDeliveryMethod::Postal, $actor);

        try {
            $pdf = $this->pdfGenerator->generate($reminder);
        } catch (Throwable $exception) {
            report($exception);

            Log::error('Postal reminder PDF generation failed', [
                'invoice_id' => $invoice->id,
                'invoice_reminder_id' => $reminder->id,
                'user_id' => $actor->id,
                'exception' => $exception,
            ]);

            $error = Str::limit($exception->getMessage(), 1000);

            $reminder->update([
                'status' => DeliveryStatus::Failed,
                'processed_at' => now(),
                'error' => $error,
            ]);

            $this->activityLogger->record($invoice, 'invoice.reminder_failed', $actor, metadata: [
                'invoice_reminder_id' => $reminder->id,
                'channel' => InvoiceDeliveryMethod::Postal->value,
                'error' => $error,
            ]);

            throw InvoiceReminderException::printFailed($error);
        }

        $reminder->update([
            'status' => DeliveryStatus::Printed,
            'processed_at' => now(),
            'error' => null,
        ]);

        $this->activityLogger->record($invoice, 'invoice.reminder_printed', $actor, metadata: [
            'invoice_reminder_id' => $reminder->id,
            'channel' => InvoiceDeliveryMethod::Postal->value,
            'outstanding_amount_cents' => $reminder->outstanding_amount_cents,
        ]);

        return [
            'pdf' => $pdf,
            'reminder' => $reminder->refresh(),
        ];
    }
}
