<?php

namespace App\Domains\Invoicing\Enums;

enum CreditNoteStatus: string
{
    case Draft = 'draft';
    case Issued = 'issued';
    case PartiallyUsed = 'partially_used';
    case FullyUsed = 'fully_used';
    case PartiallyReversed = 'partially_reversed';
    case Reversed = 'reversed';

    public function label(): string
    {
        return match ($this) {
            self::Draft => 'Brouillon',
            self::Issued => 'Émis',
            self::PartiallyUsed => 'Partiellement utilisé',
            self::FullyUsed => 'Entièrement utilisé',
            self::PartiallyReversed => 'Partiellement annulé',
            self::Reversed => 'Annulé',
        };
    }
}
