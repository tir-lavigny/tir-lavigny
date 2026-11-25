<?php

namespace App\Domains\Invoicing\Enums;

enum CampaignMemberStatus: string
{
    case Pending = 'pending';
    case Validated = 'validated';
    case Excluded = 'excluded';

    public function label(): string
    {
        return match ($this) {
            self::Pending => 'En attente',
            self::Validated => 'Validé',
            self::Excluded => 'Exclu',
        };
    }

    public function isIncluded(): bool
    {
        return $this !== self::Excluded;
    }
}
