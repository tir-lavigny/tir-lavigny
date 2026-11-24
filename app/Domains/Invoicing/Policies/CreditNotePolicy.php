<?php

namespace App\Domains\Invoicing\Policies;

use App\Domains\Invoicing\Models\CreditNote;
use App\Models\User;

class CreditNotePolicy
{
    public function viewAny(User $user): bool
    {
        return $this->canView($user);
    }

    public function view(User $user, CreditNote $creditNote): bool
    {
        return $this->canView($user);
    }

    public function create(User $user): bool
    {
        return $user->canAny(['create invoices', 'manage invoicing']);
    }

    public function update(User $user, CreditNote $creditNote): bool
    {
        return $creditNote->isDraft() && $user->canAny(['create invoices', 'manage invoicing']);
    }

    public function delete(User $user, CreditNote $creditNote): bool
    {
        return $creditNote->isDraft() && $user->canAny(['create invoices', 'manage invoicing']);
    }

    public function issue(User $user, CreditNote $creditNote): bool
    {
        return $creditNote->isDraft() && $user->canAny(['issue invoices', 'manage invoicing']);
    }

    public function apply(User $user, CreditNote $creditNote): bool
    {
        return $creditNote->isIssued() && $user->can('manage invoicing');
    }

    public function refund(User $user, CreditNote $creditNote): bool
    {
        return $creditNote->isIssued() && $user->can('manage invoicing');
    }

    public function reverse(User $user, CreditNote $creditNote): bool
    {
        return $creditNote->isIssued() && $user->can('manage invoicing');
    }

    private function canView(User $user): bool
    {
        return $user->canAny(['view invoices', 'manage invoicing']);
    }
}
