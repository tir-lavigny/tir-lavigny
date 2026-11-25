<?php

namespace App\Domains\Invoicing\Enums;

enum MembershipFeeCampaignStatus: string
{
    case Draft = 'draft';
    case Preview = 'preview';
    case Validated = 'validated';
    case Invoiced = 'invoiced';
    case Issued = 'issued';
    case Cancelled = 'cancelled';

    public function label(): string
    {
        return match ($this) {
            self::Draft => 'Brouillon',
            self::Preview => 'Aperçu',
            self::Validated => 'Validée',
            self::Invoiced => 'Facturée',
            self::Issued => 'Émise',
            self::Cancelled => 'Annulée',
        };
    }

    public function isEditable(): bool
    {
        return in_array($this, [self::Draft, self::Preview, self::Validated], true);
    }
}
