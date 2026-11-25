<?php

namespace App\Domains\Invoicing\Actions\Deliveries;

use App\Domains\Invoicing\Enums\DeliveryStatus;
use App\Domains\Invoicing\Enums\InvoiceDeliveryMethod;
use App\Domains\Invoicing\Exceptions\InvoiceDeliveryException;
use App\Domains\Invoicing\Models\Invoice;
use App\Domains\Invoicing\Models\InvoiceDelivery;
use App\Domains\Invoicing\Services\InvoiceActivityLogger;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class ChangeInvoiceDeliveryMethod
{
    public function __construct(
        private readonly InvoiceActivityLogger $activityLogger,
    ) {}

    public function execute(Invoice $invoice, InvoiceDeliveryMethod $method, User $actor): Invoice
    {
        return DB::transaction(function () use ($invoice, $method, $actor): Invoice {
            $locked = Invoice::query()->lockForUpdate()->findOrFail($invoice->id);

            if (! $locked->isDistributable()) {
                throw InvoiceDeliveryException::notDistributable();
            }

            if ($locked->delivery_method === $method) {
                return $locked;
            }

            $deliveryStarted = InvoiceDelivery::query()
                ->where('invoice_id', $locked->id)
                ->whereIn('status', [
                    DeliveryStatus::Pending,
                    DeliveryStatus::Sent,
                    DeliveryStatus::Printed,
                ])
                ->exists();

            if ($deliveryStarted) {
                throw InvoiceDeliveryException::deliveryAlreadyStarted();
            }

            if ($method === InvoiceDeliveryMethod::Email && filter_var((string) $locked->recipient_email, FILTER_VALIDATE_EMAIL) === false) {
                throw InvoiceDeliveryException::emailUnavailable();
            }

            $previous = $locked->delivery_method;
            $locked->update(['delivery_method' => $method]);

            $this->activityLogger->record($locked, 'invoice.delivery_method_changed', $actor, metadata: [
                'previous' => $previous->value,
                'current' => $method->value,
            ]);

            return $locked;
        });
    }
}
