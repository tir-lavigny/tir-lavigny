<?php

namespace App\Domains\Invoicing\Actions\Reminders;

use App\Domains\Invoicing\Enums\DeliveryStatus;
use App\Domains\Invoicing\Enums\InvoiceDeliveryMethod;
use App\Domains\Invoicing\Enums\InvoiceStatus;
use App\Domains\Invoicing\Exceptions\InvoiceReminderException;
use App\Domains\Invoicing\Models\Invoice;
use App\Domains\Invoicing\Models\InvoiceReminder;
use App\Domains\Invoicing\Services\InvoiceBalanceCalculator;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class CreateInvoiceReminder
{
    public function __construct(
        private readonly InvoiceBalanceCalculator $balances,
    ) {}

    public function execute(Invoice $invoice, InvoiceDeliveryMethod $channel, User $actor): InvoiceReminder
    {
        return DB::transaction(function () use ($invoice, $channel, $actor): InvoiceReminder {
            $locked = Invoice::query()->lockForUpdate()->findOrFail($invoice->id);

            if (! $locked->isDistributable()) {
                throw InvoiceReminderException::notDistributable();
            }

            $outstandingCents = $this->balances->outstandingCents($locked);

            if ($outstandingCents <= 0 || $locked->status === InvoiceStatus::Paid) {
                throw InvoiceReminderException::noOutstandingBalance();
            }

            if ($this->balances->daysOverdue($locked, outstandingCents: $outstandingCents) <= 0) {
                throw InvoiceReminderException::notOverdue();
            }

            $recipient = $channel === InvoiceDeliveryMethod::Email
                ? trim((string) $locked->recipient_email)
                : $this->postalRecipient($locked);

            if ($channel === InvoiceDeliveryMethod::Email && filter_var($recipient, FILTER_VALIDATE_EMAIL) === false) {
                throw InvoiceReminderException::emailUnavailable();
            }

            InvoiceReminder::query()
                ->where('invoice_id', $locked->id)
                ->where('channel', $channel->value)
                ->where('status', DeliveryStatus::Pending->value)
                ->where('created_at', '<', now()->subMinutes(15))
                ->update([
                    'status' => DeliveryStatus::Failed->value,
                    'processed_at' => now(),
                    'error' => 'Tentative de rappel expirée.',
                ]);

            $hasPendingReminder = InvoiceReminder::query()
                ->where('invoice_id', $locked->id)
                ->where('channel', $channel->value)
                ->where('status', DeliveryStatus::Pending->value)
                ->exists();

            if ($hasPendingReminder) {
                throw InvoiceReminderException::pendingReminder();
            }

            return InvoiceReminder::create([
                'invoice_id' => $locked->id,
                'channel' => $channel,
                'recipient' => $recipient,
                'outstanding_amount_cents' => $outstandingCents,
                'status' => DeliveryStatus::Pending,
                'processed_at' => null,
                'processed_by' => $actor->id,
                'error' => null,
            ]);
        });
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
