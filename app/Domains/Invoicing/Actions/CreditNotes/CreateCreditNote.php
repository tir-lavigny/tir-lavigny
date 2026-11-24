<?php

namespace App\Domains\Invoicing\Actions\CreditNotes;

use App\Domains\Invoicing\Enums\CreditNoteStatus;
use App\Domains\Invoicing\Enums\Currency;
use App\Domains\Invoicing\Enums\InvoiceStatus;
use App\Domains\Invoicing\Models\CreditNote;
use App\Domains\Invoicing\Models\Invoice;
use App\Domains\Invoicing\Services\CreditNoteDraftService;
use App\Domains\Invoicing\Services\InvoiceActivityLogger;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class CreateCreditNote
{
    public function __construct(
        private readonly CreditNoteDraftService $draftService,
        private readonly InvoiceActivityLogger $activityLogger,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public function execute(array $data, User $actor): CreditNote
    {
        return DB::transaction(function () use ($data, $actor): CreditNote {
            $invoice = Invoice::query()->whereKey((int) $data['invoice_id'])->firstOrFail();

            if (in_array($invoice->status, [InvoiceStatus::Draft, InvoiceStatus::Cancelled], true)) {
                abort(422, 'A credit note can only be created from an issued invoice.');
            }

            $prepared = $this->draftService->prepareItems($data['items']);

            $creditNote = CreditNote::create([
                'invoice_id' => $invoice->id,
                'status' => CreditNoteStatus::Draft,
                'reason' => $data['reason'],
                'issue_date' => $data['issue_date'] ?? null,
                'currency' => Currency::CHF,
                'subtotal_cents' => $prepared['subtotal_cents'],
                'total_cents' => $prepared['total_cents'],
                ...$this->draftService->snapshot($invoice),
                'created_by' => $actor->id,
            ]);

            $this->draftService->createItems($creditNote, $prepared['lines']);
            $this->activityLogger->record($invoice, 'credit_note.created', $actor, metadata: [
                'credit_note_id' => $creditNote->id,
            ]);

            return $creditNote->load('items');
        });
    }
}
