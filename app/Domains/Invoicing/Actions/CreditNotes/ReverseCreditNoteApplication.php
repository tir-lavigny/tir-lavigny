<?php

namespace App\Domains\Invoicing\Actions\CreditNotes;

use App\Domains\Invoicing\Exceptions\CreditNoteNotApplicable;
use App\Domains\Invoicing\Models\CreditNote;
use App\Domains\Invoicing\Models\CreditNoteApplication;
use App\Domains\Invoicing\Models\CreditNoteApplicationReversal;
use App\Domains\Invoicing\Models\Invoice;
use App\Domains\Invoicing\Services\CreditNoteApplicationService;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class ReverseCreditNoteApplication
{
    public function __construct(
        private readonly CreditNoteApplicationService $applications,
    ) {}

    public function execute(CreditNoteApplication $application, int $amountCents, string $reason, User $actor): CreditNoteApplicationReversal
    {
        $reason = trim($reason);

        return DB::transaction(function () use ($application, $amountCents, $reason, $actor): CreditNoteApplicationReversal {
            $locked = CreditNoteApplication::query()->lockForUpdate()->findOrFail($application->id);
            $creditNote = CreditNote::query()->lockForUpdate()->findOrFail($locked->credit_note_id);
            Invoice::query()->lockForUpdate()->findOrFail($locked->invoice_id);

            $reversed = (int) CreditNoteApplicationReversal::query()
                ->where('credit_note_application_id', $locked->id)
                ->sum('amount_cents');
            $reversible = max(0, $locked->amount_cents - $reversed);

            if ($amountCents <= 0 || $amountCents > $reversible) {
                throw CreditNoteNotApplicable::for($creditNote, 'the reversal amount exceeds the application amount.');
            }

            if ($reason === '') {
                throw CreditNoteNotApplicable::for($creditNote, 'a reversal reason is required.');
            }

            return $this->applications->reverse($locked, $amountCents, $reason, $actor);
        });
    }
}
