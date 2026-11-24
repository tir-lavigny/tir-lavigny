<?php

namespace App\Domains\Invoicing\Actions\CreditNotes;

use App\Domains\Invoicing\Enums\CreditNoteStatus;
use App\Domains\Invoicing\Events\CreditNoteIssued;
use App\Domains\Invoicing\Exceptions\CreditNoteNotIssuable;
use App\Domains\Invoicing\Models\CreditNote;
use App\Domains\Invoicing\Models\Invoice;
use App\Domains\Invoicing\Services\CreditNoteApplicationService;
use App\Domains\Invoicing\Services\CreditNoteBalanceCalculator;
use App\Domains\Invoicing\Services\CreditNoteCreditLimit;
use App\Domains\Invoicing\Services\CreditNoteDraftService;
use App\Domains\Invoicing\Services\CreditNoteNumberGenerator;
use App\Domains\Invoicing\Services\CreditNoteStatusCalculator;
use App\Domains\Invoicing\Services\CustomerAccountLedger;
use App\Domains\Invoicing\Services\InvoiceActivityLogger;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class IssueCreditNote
{
    public function __construct(
        private readonly CreditNoteNumberGenerator $numberGenerator,
        private readonly CreditNoteDraftService $draftService,
        private readonly CreditNoteCreditLimit $creditLimit,
        private readonly CreditNoteBalanceCalculator $balances,
        private readonly CreditNoteStatusCalculator $statuses,
        private readonly CreditNoteApplicationService $applications,
        private readonly CustomerAccountLedger $ledger,
        private readonly InvoiceActivityLogger $activityLogger,
    ) {}

    public function execute(CreditNote $creditNote, User $actor): CreditNote
    {
        $issued = DB::transaction(function () use ($creditNote, $actor): CreditNote {
            $locked = CreditNote::query()->lockForUpdate()->findOrFail($creditNote->id);
            $invoice = Invoice::query()->lockForUpdate()->findOrFail($locked->invoice_id);

            if (! $locked->isDraft()) {
                throw CreditNoteNotIssuable::for($locked, 'the credit note is not a draft.');
            }

            if (! $locked->items()->exists()) {
                throw CreditNoteNotIssuable::for($locked, 'the credit note has no lines.');
            }

            if ($locked->total_cents <= 0) {
                throw CreditNoteNotIssuable::for($locked, 'the total must be positive.');
            }

            $remaining = $this->creditLimit->remainingFor($invoice);

            if ($locked->total_cents > $remaining) {
                throw CreditNoteNotIssuable::for($locked, 'the amount exceeds the remaining creditable amount.');
            }

            $issueDate = $locked->issue_date ?? now()->startOfDay();
            $number = $this->numberGenerator->allocate((int) $issueDate->format('Y'));

            $locked->update([
                'credit_note_number' => $number,
                'status' => CreditNoteStatus::Issued,
                'issue_date' => $issueDate,
                'issued_at' => now(),
                ...$this->draftService->snapshot($invoice),
            ]);

            $this->ledger->recordCreditNoteIssued($locked, $actor);
            $this->applications->applyAvailableToOriginalInvoice($locked, $actor);
            $this->statuses->refresh($locked);
            $this->activityLogger->record($invoice, 'credit_note.issued', $actor, metadata: [
                'credit_note_id' => $locked->id,
                'credit_note_number' => $number,
                'available_cents' => $this->balances->availableCents($locked->refresh()),
            ]);

            return $locked->load('items', 'applications');
        });

        event(new CreditNoteIssued($issued));

        return $issued;
    }
}
