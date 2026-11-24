<?php

namespace App\Domains\Invoicing\Actions\CreditNotes;

use App\Domains\Invoicing\Enums\CreditNoteReversalStatus;
use App\Domains\Invoicing\Enums\Currency;
use App\Domains\Invoicing\Exceptions\CreditNoteNotApplicable;
use App\Domains\Invoicing\Models\CreditNote;
use App\Domains\Invoicing\Models\CreditNoteReversal;
use App\Domains\Invoicing\Models\CreditNoteReversalItem;
use App\Domains\Invoicing\Services\CreditNoteBalanceCalculator;
use App\Domains\Invoicing\Services\InvoiceActivityLogger;
use App\Models\User;
use App\Support\Money;
use Illuminate\Support\Facades\DB;

class CreateCreditNoteReversal
{
    public function __construct(
        private readonly CreditNoteBalanceCalculator $balances,
        private readonly InvoiceActivityLogger $activityLogger,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public function execute(CreditNote $creditNote, array $data, User $actor): CreditNoteReversal
    {
        return DB::transaction(function () use ($creditNote, $data, $actor): CreditNoteReversal {
            $locked = CreditNote::query()->lockForUpdate()->findOrFail($creditNote->id);

            if (! $locked->isIssued()) {
                throw CreditNoteNotApplicable::for($locked, 'the credit note is not issued.');
            }

            if ($this->balances->appliedCents($locked) > 0) {
                throw CreditNoteNotApplicable::for($locked, 'credit applications must be reversed first.');
            }

            $items = array_values($data['items']);
            $totalCents = 0;

            foreach ($items as $item) {
                $totalCents += Money::toCents($item['amount'] ?? null) ?? 0;
            }

            if ($totalCents <= 0) {
                throw CreditNoteNotApplicable::for($locked, 'the reversal amount must be positive.');
            }

            if ($totalCents > $this->balances->availableCents($locked)) {
                throw CreditNoteNotApplicable::for($locked, 'the reversal exceeds the available credit.');
            }

            $reversal = CreditNoteReversal::create([
                'credit_note_id' => $locked->id,
                'status' => CreditNoteReversalStatus::Draft,
                'reason' => $data['reason'],
                'issue_date' => $data['issue_date'] ?? null,
                'currency' => Currency::CHF,
                'total_cents' => $totalCents,
                'recipient_name' => $locked->recipient_name,
                'recipient_address' => $locked->recipient_address,
                'recipient_postal_code' => $locked->recipient_postal_code,
                'recipient_city' => $locked->recipient_city,
                'recipient_country' => $locked->recipient_country,
                'recipient_email' => $locked->recipient_email,
                'creditor_name' => $locked->creditor_name,
                'creditor_address' => $locked->creditor_address,
                'creditor_postal_code' => $locked->creditor_postal_code,
                'creditor_city' => $locked->creditor_city,
                'creditor_country' => $locked->creditor_country,
                'creditor_iban' => $locked->creditor_iban,
                'created_by' => $actor->id,
            ]);

            foreach ($items as $index => $item) {
                CreditNoteReversalItem::create([
                    'credit_note_reversal_id' => $reversal->id,
                    'credit_note_item_id' => $item['credit_note_item_id'] ?? null,
                    'description' => $item['description'],
                    'amount_cents' => Money::toCents($item['amount']) ?? 0,
                    'position' => $index + 1,
                ]);
            }

            $this->activityLogger->record($locked->invoice, 'credit_note.reversal_created', $actor, metadata: [
                'credit_note_id' => $locked->id,
                'credit_note_reversal_id' => $reversal->id,
            ]);

            return $reversal->load('items');
        });
    }
}
