<?php

namespace App\Domains\Invoicing\Actions\Customers;

use App\Domains\Invoicing\Exceptions\CannotInvoiceAnonymizedMember;
use App\Domains\Invoicing\Models\BillingCustomer;
use App\Domains\Invoicing\Support\MemberBillingProfileMapper;
use App\Models\Member;

class SyncMemberBillingCustomer
{
    public function __construct(
        private readonly MemberBillingProfileMapper $mapper,
    ) {}

    public function execute(Member $member): BillingCustomer
    {
        if ($member->isAnonymized()) {
            throw CannotInvoiceAnonymizedMember::forMember($member->id);
        }

        $attributes = $this->mapper->map($member);
        $billingCustomer = BillingCustomer::firstOrNew(['member_id' => $member->id]);

        if ($billingCustomer->exists) {
            unset($attributes['preferred_invoice_delivery']);
        } else {
            $billingCustomer->sync_with_member = true;
        }

        $billingCustomer->fill($attributes);
        $billingCustomer->last_synced_at = now();
        $billingCustomer->save();

        return $billingCustomer;
    }
}
