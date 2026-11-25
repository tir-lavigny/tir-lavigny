<?php

namespace App\Domains\Invoicing\Policies;

use App\Domains\Invoicing\Enums\InvoiceDeliveryMethod;
use App\Domains\Invoicing\Models\Invoice;
use App\Models\User;

class InvoicePolicy
{
    public function viewAny(User $user): bool
    {
        return $this->canView($user);
    }

    public function view(User $user, Invoice $invoice): bool
    {
        return $this->canView($user);
    }

    public function create(User $user): bool
    {
        return $user->canAny(['create invoices', 'manage invoicing']);
    }

    public function update(User $user, Invoice $invoice): bool
    {
        return $invoice->isDraft() && $user->canAny(['create invoices', 'manage invoicing']);
    }

    public function delete(User $user, Invoice $invoice): bool
    {
        return $invoice->isDraft() && $user->canAny(['create invoices', 'manage invoicing']);
    }

    public function issue(User $user, Invoice $invoice): bool
    {
        return $invoice->isDraft() && $user->canAny(['issue invoices', 'manage invoicing']);
    }

    public function cancel(User $user, Invoice $invoice): bool
    {
        return $invoice->isCancellable() && $user->canAny(['cancel invoices', 'manage invoicing']);
    }

    public function send(User $user, Invoice $invoice): bool
    {
        return $invoice->isDistributable()
            && $invoice->delivery_method === InvoiceDeliveryMethod::Email
            && $user->canAny(['send invoices', 'manage invoicing']);
    }

    public function print(User $user, Invoice $invoice): bool
    {
        return $invoice->isDistributable()
            && $invoice->delivery_method === InvoiceDeliveryMethod::Postal
            && $user->canAny(['send invoices', 'manage invoicing']);
    }

    public function changeDeliveryMethod(User $user, Invoice $invoice): bool
    {
        return $invoice->isDistributable() && $user->canAny(['send invoices', 'manage invoicing']);
    }

    public function sendReminder(User $user, Invoice $invoice): bool
    {
        return $this->canRemind($invoice)
            && $user->canAny(['manage invoice reminders', 'manage invoicing']);
    }

    public function printReminder(User $user, Invoice $invoice): bool
    {
        return $this->canRemind($invoice)
            && $user->canAny(['manage invoice reminders', 'manage invoicing']);
    }

    private function canView(User $user): bool
    {
        return $user->canAny(['view invoices', 'manage invoicing']);
    }

    private function canRemind(Invoice $invoice): bool
    {
        return $invoice->isDistributable()
            && $invoice->due_date !== null
            && $invoice->due_date->lt(today());
    }
}
