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
            self::Fee => 'Frais / prestation',
            self::Discount => 'Remise',
            self::Adjustment => 'Ajustement',
        };
    }

    public function description(): string
    {
        return match ($this) {
            self::Product => "Vente d'un article.",
            self::Fee => 'Cotisation, participation ou service.',
            self::Discount => 'Réduction accordée, montant négatif.',
            self::Adjustment => 'Correction positive ou négative.',
        };
    }
}
