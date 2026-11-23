<?php

namespace App\Domains\Invoicing\Support;

use App\Domains\Invoicing\Enums\BillingCustomerType;
use App\Domains\Invoicing\Enums\InvoiceDeliveryMethod;
use App\Models\Member;

final class MemberBillingProfileMapper
{
    /**
     * @return array<string, mixed>
     */
    public function map(Member $member): array
    {
        return [
            'type' => BillingCustomerType::Individual,
            'first_name' => $member->first_name,
            'last_name' => $member->last_name,
            'organization_name' => null,
            'address' => $member->street ?? '',
            'postal_code' => $member->npa ?? '',
            'city' => $member->city ?? '',
            'country' => CountryCode::normalize($member->country),
            'email' => $member->email,
            'phone' => $member->tel,
            'preferred_invoice_delivery' => $this->preferredDeliveryMethod($member),
        ];
    }

    public function preferredDeliveryMethod(Member $member): InvoiceDeliveryMethod
    {
        return filled($member->email)
            ? InvoiceDeliveryMethod::Email
            : InvoiceDeliveryMethod::Postal;
    }
}
