<?php

namespace App\Http\Requests\BackOffice\Invoicing\Concerns;

use App\Domains\Invoicing\Enums\InvoiceDeliveryMethod;
use App\Domains\Invoicing\Models\BillingCustomer;
use Illuminate\Validation\Validator;

trait ValidatesInvoiceDelivery
{
    protected function validateInvoiceDelivery(Validator $validator): void
    {
        if ($validator->errors()->has('billing_customer_id')) {
            return;
        }

        $billingCustomer = BillingCustomer::query()->find((int) $this->input('billing_customer_id'));

        if (
            $billingCustomer !== null
            && $this->input('delivery_method') === InvoiceDeliveryMethod::Email->value
            && blank($billingCustomer->email)
        ) {
            $validator->errors()->add(
                'delivery_method',
                "Ce client n'a pas d'adresse email. Choisissez l'envoi postal.",
            );
        }
    }
}
