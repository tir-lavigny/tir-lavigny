<?php

namespace App;

enum MemberStatus: string
{
    case ACTIVE = 'active';
    case PASSIVE = 'passive';

    public function label(): string
    {
        return match ($this) {
            self::ACTIVE => 'Actif',
            self::PASSIVE => 'Passif',
        };
    }

    public function davGroup(): DavGroupLabels
    {
        return match ($this) {
            self::ACTIVE => DavGroupLabels::ACTIVE_MEMBERS,
            self::PASSIVE => DavGroupLabels::PASSIVE_MEMBERS,
        };
    }
}
