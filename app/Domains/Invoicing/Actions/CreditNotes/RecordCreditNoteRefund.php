<?php

namespace App\Domains\Invoicing\Actions\CreditNotes;

use App\Domains\Invoicing\Exceptions\CreditNoteNotApplicable;
use App\Domains\Invoicing\Models\CreditNote;
use App\Domains\Invoicing\Models\CreditNoteRefund;
use App\Domains\Invoicing\Services\CreditNoteBalanceCalculator;
use App\Domains\Invoicing\Services\CreditNoteStatusCalculator;
use App\Domains\Invoicing\Services\CustomerAccountLedger;
use App\Domains\Invoicing\Services\InvoiceActivityLogger;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class RecordCreditNoteRefund
{
    public function __construct(
        private readonly CreditNoteBalanceCalculator $balances,
        private readonly CreditNoteStatusCalculator $statuses,
        private readonly CustomerAccountLedger $ledger,
        private readonly InvoiceActivityLogger $activityLogger,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public function execute(CreditNote $creditNote, array $data, User $actor): CreditNoteRefund
    {
        return DB::transaction(function () use ($creditNote, $data, $actor): CreditNoteRefund {
            $locked = CreditNote::query()->lockForUpdate()->findOrFail($creditNote->id);
            $amountCents = (int) $data['amount_cents'];

            if ($amountCents <= 0 || $amountCents > $this->balances->availableCents($locked)) {
                throw CreditNoteNotApplicable::for($locked, 'the refund exceeds the available credit.');
            }

            $refund = CreditNoteRefund::create([
                'credit_note_id' => $locked->id,
                'amount_cents' => $amountCents,
                'refund_date' => $data['refund_date'],
                'payment_method' => $data['payment_method'],
                'reference' => $data['reference'] ?? null,
                'notes' => $data['notes'] ?? null,
                'created_by' => $actor->id,
            ]);

            $this->ledger->recordCreditNoteRefunded($refund, $actor);
            $this->statuses->refresh($locked);
            $this->activityLogger->record($locked->invoice, 'credit_note.refunded', $actor, metadata: [
                'credit_note_id' => $locked->id,
                'refund_id' => $refund->id,
                'amount_cents' => $amountCents,
            ]);

            return $refund;
        });
    }
}
