<?php

namespace App\Domains\Invoicing\Enums;

enum InvoiceItemType: string
{
    case Product = 'product';
    case Fee = 'fee';
    case Discount = 'discount';
    case Adjustment = 'adjustment';

    public function label(): string
    {
        return match ($this) {
            self::Product => 'Produit',
            self::Fee => 'Taxe',
            self::Discount => 'Remise',
            self::Adjustment => 'Ajustement',
        };
    }
}
