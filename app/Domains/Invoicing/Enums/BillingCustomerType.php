<?php

namespace App\Domains\Invoicing\Enums;

enum BillingCustomerType: string
{
    case Individual = 'individual';
    case Organization = 'organization';

    public function label(): string
    {
        return match ($this) {
            self::Individual => 'Particulier',
            self::Organization => 'Organisation',
        };
    }
}
