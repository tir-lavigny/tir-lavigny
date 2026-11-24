<?php

namespace App\Domains\Invoicing\Enums;

enum InvoiceType: string
{
    case MembershipFee = 'membership_fee';
    case OneOff = 'one_off';

    public function label(): string
    {
        return match ($this) {
            self::MembershipFee => 'Cotisation',
            self::OneOff => 'Facture ponctuelle',
        };
    }
}
