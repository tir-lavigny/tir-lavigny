<?php

namespace App\Domains\Invoicing\Policies;

use App\Domains\Invoicing\Models\MembershipFeeType;
use App\Models\User;

class MembershipFeeTypePolicy
{
    public function viewAny(User $user): bool
    {
        return $this->canView($user);
    }

    public function view(User $user, MembershipFeeType $feeType): bool
    {
        return $this->canView($user);
    }

    public function create(User $user): bool
    {
        return $this->canManage($user);
    }

    public function update(User $user, MembershipFeeType $feeType): bool
    {
        return $this->canManage($user);
    }

    private function canView(User $user): bool
    {
        return $user->canAny(['view invoices', 'manage invoicing', 'manage membership fees']);
    }

    private function canManage(User $user): bool
    {
        return $user->canAny(['manage invoicing', 'manage membership fees']);
    }
}
