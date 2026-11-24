<?php

namespace App\Domains\Invoicing\Actions\CreditNotes;

use App\Domains\Invoicing\Exceptions\CreditNoteNotDraft;
use App\Domains\Invoicing\Models\CreditNote;
use App\Domains\Invoicing\Models\Invoice;
use App\Domains\Invoicing\Services\CreditNoteDraftService;
use App\Domains\Invoicing\Services\InvoiceActivityLogger;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class UpdateDraftCreditNote
{
    public function __construct(
        private readonly CreditNoteDraftService $draftService,
        private readonly InvoiceActivityLogger $activityLogger,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public function execute(CreditNote $creditNote, array $data, User $actor): CreditNote
    {
        return DB::transaction(function () use ($creditNote, $data, $actor): CreditNote {
            $locked = CreditNote::query()->lockForUpdate()->findOrFail($creditNote->id);

            if (! $locked->isDraft()) {
                throw CreditNoteNotDraft::for($locked);
            }

            $invoice = Invoice::query()->findOrFail($locked->invoice_id);
            $prepared = $this->draftService->prepareItems($data['items']);

            $locked->update([
                'reason' => $data['reason'],
                'issue_date' => $data['issue_date'] ?? null,
                'subtotal_cents' => $prepared['subtotal_cents'],
                'total_cents' => $prepared['total_cents'],
                ...$this->draftService->snapshot($invoice),
            ]);

            $this->draftService->replaceItems($locked, $prepared['lines']);
            $this->activityLogger->record($invoice, 'credit_note.updated', $actor, metadata: [
                'credit_note_id' => $locked->id,
            ]);

            return $locked->load('items');
        });
    }
}
