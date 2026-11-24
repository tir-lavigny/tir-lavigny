<?php

namespace App\Domains\Invoicing\Actions\Customers;

use App\Domains\Invoicing\Models\BillingCustomer;

class UpdateBillingCustomer
{
    /**
     * @param  array<string, mixed>  $data
     */
    public function execute(BillingCustomer $billingCustomer, array $data): BillingCustomer
    {
        $billingCustomer->update($data);

        return $billingCustomer;
    }
}
