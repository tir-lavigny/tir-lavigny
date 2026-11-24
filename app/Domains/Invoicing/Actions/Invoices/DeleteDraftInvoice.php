<?php

namespace App\Domains\Invoicing\Actions\Invoices;

use App\Domains\Invoicing\Enums\InvoiceStatus;
use App\Domains\Invoicing\Exceptions\InvoiceNotDraft;
use App\Domains\Invoicing\Models\Invoice;
use App\Domains\Invoicing\Services\InvoiceActivityLogger;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class DeleteDraftInvoice
{
    public function __construct(
        private readonly InvoiceActivityLogger $activityLogger,
    ) {}

    public function execute(Invoice $invoice, User $actor): void
    {
        DB::transaction(function () use ($invoice, $actor): void {
            $locked = Invoice::query()->lockForUpdate()->findOrFail($invoice->id);

            if ($locked->status !== InvoiceStatus::Draft) {
                throw InvoiceNotDraft::for($locked);
            }

            $this->activityLogger->record($locked, 'invoice.deleted', $actor);
            $locked->delete();
        });
    }
}
