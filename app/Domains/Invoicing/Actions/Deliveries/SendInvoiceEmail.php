<?php

namespace App\Domains\Invoicing\Actions\Deliveries;

use App\Domains\Invoicing\Enums\DeliveryStatus;
use App\Domains\Invoicing\Enums\InvoiceDeliveryMethod;
use App\Domains\Invoicing\Exceptions\InvoiceDeliveryException;
use App\Domains\Invoicing\Jobs\SendInvoiceEmailJob;
use App\Domains\Invoicing\Models\Invoice;
use App\Domains\Invoicing\Models\InvoiceDelivery;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class SendInvoiceEmail
{
    public function execute(Invoice $invoice, User $actor, ?string $batchId = null): InvoiceDelivery
    {
        $delivery = DB::transaction(function () use ($invoice, $actor, $batchId): InvoiceDelivery {
            $locked = Invoice::query()->lockForUpdate()->findOrFail($invoice->id);

            if (! $locked->isDistributable()) {
                throw InvoiceDeliveryException::notDistributable();
            }

            if ($locked->delivery_method !== InvoiceDeliveryMethod::Email) {
                throw InvoiceDeliveryException::notEmail();
            }

            $email = trim((string) $locked->recipient_email);

            if (filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
                throw InvoiceDeliveryException::emailUnavailable();
            }

            $locked->loadMissing('document');

            if (! $locked->document?->isStored()) {
                throw InvoiceDeliveryException::documentUnavailable();
            }

            InvoiceDelivery::query()
                ->where('invoice_id', $locked->id)
                ->where('channel', InvoiceDeliveryMethod::Email)
                ->where('status', DeliveryStatus::Pending)
                ->where('created_at', '<', now()->subMinutes(15))
                ->update([
                    'status' => DeliveryStatus::Failed,
                    'processed_at' => now(),
                    'error' => 'Tentative d’envoi expirée.',
                ]);

            $hasPendingAttempt = InvoiceDelivery::query()
                ->where('invoice_id', $locked->id)
                ->where('channel', InvoiceDeliveryMethod::Email)
                ->where('status', DeliveryStatus::Pending)
                ->exists();

            if ($hasPendingAttempt) {
                throw InvoiceDeliveryException::pendingEmail();
            }

            $delivery = InvoiceDelivery::create([
                'invoice_id' => $locked->id,
                'channel' => InvoiceDeliveryMethod::Email,
                'recipient' => $email,
                'batch_id' => $batchId ?? (string) Str::uuid(),
                'status' => DeliveryStatus::Pending,
                'processed_at' => null,
                'processed_by' => $actor->id,
                'notes' => null,
                'error' => null,
            ]);

            SendInvoiceEmailJob::dispatch($delivery->id)->afterCommit();

            return $delivery;
        });

        return $delivery->refresh();
    }
}
