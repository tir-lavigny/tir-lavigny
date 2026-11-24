<?php

namespace App\Domains\Invoicing\Actions\CreditNotes;

use App\Domains\Invoicing\Exceptions\CreditNoteNotDraft;
use App\Domains\Invoicing\Models\CreditNote;
use App\Domains\Invoicing\Models\Invoice;
use App\Domains\Invoicing\Services\InvoiceActivityLogger;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class DeleteDraftCreditNote
{
    public function __construct(
        private readonly InvoiceActivityLogger $activityLogger,
    ) {}

    public function execute(CreditNote $creditNote, User $actor): void
    {
        DB::transaction(function () use ($creditNote, $actor): void {
            $locked = CreditNote::query()->lockForUpdate()->findOrFail($creditNote->id);

            if (! $locked->isDraft()) {
                throw CreditNoteNotDraft::for($locked);
            }

            $invoice = Invoice::query()->findOrFail($locked->invoice_id);
            $this->activityLogger->record($invoice, 'credit_note.deleted', $actor, metadata: [
                'credit_note_id' => $locked->id,
            ]);

            $locked->delete();
        });
    }
}
