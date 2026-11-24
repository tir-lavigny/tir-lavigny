<?php

namespace App\Domains\Invoicing\Actions\Deliveries;

use App\Domains\Invoicing\Enums\DeliveryStatus;
use App\Domains\Invoicing\Enums\InvoiceDeliveryMethod;
use App\Domains\Invoicing\Exceptions\InvoiceDeliveryException;
use App\Domains\Invoicing\Models\Invoice;
use App\Domains\Invoicing\Models\InvoiceDelivery;
use App\Domains\Invoicing\Services\ArchivedInvoicePdfMerger;
use App\Domains\Invoicing\Services\InvoiceActivityLogger;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class PrintInvoiceDocuments
{
    public function __construct(
        private readonly ArchivedInvoicePdfMerger $merger,
        private readonly InvoiceActivityLogger $activityLogger,
    ) {}

    /**
     * @param  array<int, int>  $invoiceIds
     * @return array{pdf: string, invoices: Collection<int, Invoice>, batch_id: string}
     */
    public function execute(array $invoiceIds, User $actor, bool $onlyUnprinted = true): array
    {
        $ids = array_values(array_unique(array_map('intval', $invoiceIds)));

        if ($ids === []) {
            throw InvoiceDeliveryException::noInvoices();
        }

        $invoices = Invoice::query()
            ->with('document')
            ->whereIn('id', $ids)
            ->orderBy('issue_date')
            ->orderBy('invoice_number')
            ->get();

        if ($onlyUnprinted) {
            $printedIds = InvoiceDelivery::query()
                ->whereIn('invoice_id', $invoices->modelKeys())
                ->where('channel', InvoiceDeliveryMethod::Postal)
                ->where('status', DeliveryStatus::Printed)
                ->pluck('invoice_id')
                ->all();

            $invoices = $invoices
                ->reject(fn (Invoice $invoice): bool => in_array($invoice->id, $printedIds, true))
                ->values();
        }

        if ($invoices->isEmpty()) {
            throw InvoiceDeliveryException::noInvoices();
        }

        foreach ($invoices as $invoice) {
            if (! $invoice->isDistributable()) {
                throw InvoiceDeliveryException::notDistributable();
            }

            if ($invoice->delivery_method !== InvoiceDeliveryMethod::Postal) {
                throw InvoiceDeliveryException::notPostal();
            }

            if (! $invoice->document?->isStored()) {
                throw InvoiceDeliveryException::documentUnavailable();
            }
        }

        $pdf = $this->merger->merge($invoices);
        $batchId = (string) Str::uuid();

        DB::transaction(function () use ($invoices, $actor, $batchId): void {
            foreach ($invoices as $invoice) {
                $delivery = InvoiceDelivery::create([
                    'invoice_id' => $invoice->id,
                    'channel' => InvoiceDeliveryMethod::Postal,
                    'recipient' => $this->postalRecipient($invoice),
                    'batch_id' => $batchId,
                    'status' => DeliveryStatus::Printed,
                    'processed_at' => now(),
                    'processed_by' => $actor->id,
                    'notes' => null,
                    'error' => null,
                ]);

                $this->activityLogger->record($invoice, 'invoice.printed', $actor, metadata: [
                    'invoice_delivery_id' => $delivery->id,
                    'batch_id' => $batchId,
                ]);
            }
        });

        return [
            'pdf' => $pdf,
            'invoices' => $invoices,
            'batch_id' => $batchId,
        ];
    }

    private function postalRecipient(Invoice $invoice): string
    {
        return implode(', ', array_filter([
            $invoice->recipient_name,
            $invoice->recipient_address,
            trim($invoice->recipient_postal_code.' '.$invoice->recipient_city),
            $invoice->recipient_country,
        ], fn (?string $value): bool => filled($value)));
    }
}
