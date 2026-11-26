<?php

namespace App\Domains\Invoicing\Actions\Reminders;

use App\Domains\Invoicing\Enums\InvoiceDeliveryMethod;
use App\Domains\Invoicing\Jobs\SendInvoiceReminderEmailJob;
use App\Domains\Invoicing\Models\Invoice;
use App\Domains\Invoicing\Models\InvoiceReminder;
use App\Models\User;

class SendInvoiceReminderEmail
{
    public function __construct(
        private readonly CreateInvoiceReminder $createReminder,
    ) {}

    public function execute(Invoice $invoice, User $actor): InvoiceReminder
    {
        $reminder = $this->createReminder->execute($invoice, InvoiceDeliveryMethod::Email, $actor);

        SendInvoiceReminderEmailJob::dispatch($reminder->id)->afterCommit();

        return $reminder->refresh();
    }
}
