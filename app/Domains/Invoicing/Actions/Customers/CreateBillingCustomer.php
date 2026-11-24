<?php

namespace App\Domains\Invoicing\Actions\Customers;

use App\Domains\Invoicing\Models\BillingCustomer;

class CreateBillingCustomer
{
    /**
     * @param  array<string, mixed>  $data
     */
    public function execute(array $data): BillingCustomer
    {
        return BillingCustomer::create($data);
    }
}
