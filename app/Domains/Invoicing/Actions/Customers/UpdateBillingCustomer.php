<?php

namespace App\Domains\Invoicing\Actions\Customers;

use App\Domains\Invoicing\Exceptions\CannotInvoiceAnonymizedMember;
use App\Domains\Invoicing\Models\BillingCustomer;

class UpdateBillingCustomer
{
    public function __construct(
        private readonly SyncMemberBillingCustomer $syncMemberBillingCustomer,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public function execute(BillingCustomer $billingCustomer, array $data): BillingCustomer
    {
        $billingCustomer->update($data);
        $billingCustomer->refresh();

        if ($billingCustomer->member_id === null || ! $billingCustomer->sync_with_member) {
            return $billingCustomer;
        }

        try {
            return $this->syncMemberBillingCustomer->execute($billingCustomer->member);
        } catch (CannotInvoiceAnonymizedMember) {
            return $billingCustomer;
        }
    }
}
