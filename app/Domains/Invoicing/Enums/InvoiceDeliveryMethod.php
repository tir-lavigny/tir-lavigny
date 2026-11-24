<?php

namespace App\Domains\Invoicing\Enums;

enum InvoiceDeliveryMethod: string
{
    case Email = 'email';
    case Postal = 'postal';

    public function label(): string
    {
        return match ($this) {
            self::Email => 'Email',
            self::Postal => 'Postal',
        };
    }
}
