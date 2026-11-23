<?php

namespace App\Domains\Invoicing\Policies;

use App\Domains\Invoicing\Models\BillingCustomer;
use App\Models\User;

class BillingCustomerPolicy
{
    public function viewAny(User $user): bool
    {
        return $this->canView($user);
    }

    public function view(User $user, BillingCustomer $billingCustomer): bool
    {
        return $this->canView($user);
    }

    public function create(User $user): bool
    {
        return $user->can('manage invoicing');
    }

    public function update(User $user, BillingCustomer $billingCustomer): bool
    {
        return $user->can('manage invoicing');
    }

    public function delete(User $user, BillingCustomer $billingCustomer): bool
    {
        return $user->can('manage invoicing');
    }

    private function canView(User $user): bool
    {
        return $user->canAny(['view invoices', 'manage invoicing']);
    }
}
