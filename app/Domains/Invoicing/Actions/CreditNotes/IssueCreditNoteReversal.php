<?php

namespace App\Domains\Invoicing\Actions\CreditNotes;

use App\Domains\Invoicing\Enums\CreditNoteReversalStatus;
use App\Domains\Invoicing\Exceptions\CreditNoteReversalNotIssuable;
use App\Domains\Invoicing\Models\CreditNote;
use App\Domains\Invoicing\Models\CreditNoteReversal;
use App\Domains\Invoicing\Services\CreditNoteBalanceCalculator;
use App\Domains\Invoicing\Services\CreditNoteReversalNumberGenerator;
use App\Domains\Invoicing\Services\CreditNoteStatusCalculator;
use App\Domains\Invoicing\Services\CustomerAccountLedger;
use App\Domains\Invoicing\Services\InvoiceActivityLogger;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class IssueCreditNoteReversal
{
    public function __construct(
        private readonly CreditNoteReversalNumberGenerator $numberGenerator,
        private readonly CreditNoteBalanceCalculator $balances,
        private readonly CreditNoteStatusCalculator $statuses,
        private readonly CustomerAccountLedger $ledger,
        private readonly InvoiceActivityLogger $activityLogger,
    ) {}

    public function execute(CreditNoteReversal $reversal, User $actor): CreditNoteReversal
    {
        return DB::transaction(function () use ($reversal, $actor): CreditNoteReversal {
            $locked = CreditNoteReversal::query()->lockForUpdate()->findOrFail($reversal->id);
            $creditNote = CreditNote::query()->lockForUpdate()->findOrFail($locked->credit_note_id);

            if (! $locked->isDraft()) {
                throw CreditNoteReversalNotIssuable::for($locked, 'the reversal is not a draft.');
            }

            if (! $locked->items()->exists()) {
                throw CreditNoteReversalNotIssuable::for($locked, 'the reversal has no lines.');
            }

            if ($this->balances->appliedCents($creditNote) > 0) {
                throw CreditNoteReversalNotIssuable::for($locked, 'credit applications must be reversed first.');
            }

            if ($this->balances->refundedCents($creditNote) > 0) {
                throw CreditNoteReversalNotIssuable::for($locked, 'refunds must be reconciled first.');
            }

            if ($locked->total_cents > $this->balances->availableCents($creditNote)) {
                throw CreditNoteReversalNotIssuable::for($locked, 'the reversal exceeds the available credit.');
            }

            $issueDate = $locked->issue_date ?? now()->startOfDay();
            $number = $this->numberGenerator->allocate((int) $issueDate->format('Y'));

            $locked->update([
                'credit_note_reversal_number' => $number,
                'status' => CreditNoteReversalStatus::Issued,
                'issue_date' => $issueDate,
                'issued_at' => now(),
            ]);

            $this->ledger->recordCreditNoteReversed($locked, $actor);
            $this->statuses->refresh($creditNote);
            $this->activityLogger->record($creditNote->invoice, 'credit_note.reversal_issued', $actor, metadata: [
                'credit_note_id' => $creditNote->id,
                'credit_note_reversal_id' => $locked->id,
                'credit_note_reversal_number' => $number,
            ]);

            return $locked->load('items');
        });
    }
}
