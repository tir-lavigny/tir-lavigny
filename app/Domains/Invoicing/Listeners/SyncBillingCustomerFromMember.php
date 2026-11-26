<?php

namespace App\Domains\Invoicing\Listeners;

use App\Domains\Invoicing\Actions\Customers\SyncMemberBillingCustomer;
use App\Domains\Invoicing\Exceptions\CannotInvoiceAnonymizedMember;
use App\Domains\Invoicing\Models\BillingCustomer;
use App\Events\MemberProfileUpdated;
use Illuminate\Support\Facades\Log;
use Throwable;

class SyncBillingCustomerFromMember
{
    public function __construct(
        private readonly SyncMemberBillingCustomer $sync,
    ) {}

    public function handle(MemberProfileUpdated $event): void
    {
        $member = $event->member;

        if ($member->isAnonymized()) {
            return;
        }

        $billingCustomer = BillingCustomer::query()
            ->where('member_id', $member->id)
            ->first();

        if ($billingCustomer === null || ! $billingCustomer->sync_with_member) {
            return;
        }

        try {
            $this->sync->execute($member);
        } catch (CannotInvoiceAnonymizedMember) {
            return;
        } catch (Throwable $exception) {
            report($exception);

            Log::error('Billing customer auto-sync failed', [
                'member_id' => $member->id,
                'billing_customer_id' => $billingCustomer->id,
                'exception' => $exception,
            ]);
        }
    }
}
