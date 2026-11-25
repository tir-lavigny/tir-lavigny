<?php

namespace App\Domains\Invoicing\Policies;

use App\Domains\Invoicing\Enums\InvoiceStatus;
use App\Domains\Invoicing\Enums\MembershipFeeCampaignStatus;
use App\Domains\Invoicing\Models\MembershipFeeCampaign;
use App\Models\User;

class MembershipFeeCampaignPolicy
{
    public function viewAny(User $user): bool
    {
        return $this->canView($user);
    }

    public function view(User $user, MembershipFeeCampaign $campaign): bool
    {
        return $this->canView($user);
    }

    public function create(User $user): bool
    {
        return $this->canManage($user);
    }

    public function update(User $user, MembershipFeeCampaign $campaign): bool
    {
        return $campaign->isEditable() && $this->canManage($user);
    }

    public function preview(User $user, MembershipFeeCampaign $campaign): bool
    {
        return $campaign->isEditable() && $this->canManage($user);
    }

    public function validate(User $user, MembershipFeeCampaign $campaign): bool
    {
        return $campaign->isEditable() && $this->canManage($user);
    }

    public function generateInvoices(User $user, MembershipFeeCampaign $campaign): bool
    {
        return $campaign->status === MembershipFeeCampaignStatus::Validated
            && $this->canManage($user);
    }

    public function issueInvoices(User $user, MembershipFeeCampaign $campaign): bool
    {
        return in_array($campaign->status, [
            MembershipFeeCampaignStatus::Invoiced,
            MembershipFeeCampaignStatus::Issued,
        ], true) && $user->canAny(['issue invoices', 'manage invoicing']);
    }

    public function manageMembers(User $user, MembershipFeeCampaign $campaign): bool
    {
        return $campaign->isEditable() && $this->canManage($user);
    }

    public function refreshBaseAmounts(User $user, MembershipFeeCampaign $campaign): bool
    {
        return $campaign->isEditable()
            && ! $campaign->invoices()->exists()
            && $this->canManage($user);
    }

    public function delete(User $user, MembershipFeeCampaign $campaign): bool
    {
        return ! $campaign->invoices()
            ->where('status', '!=', InvoiceStatus::Draft->value)
            ->exists()
            && $this->canManage($user);
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
