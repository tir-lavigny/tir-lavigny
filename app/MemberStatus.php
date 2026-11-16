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

    /** @return array<int, string> */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }

    /** @return array<string, string> */
    public static function labels(): array
    {
        return array_reduce(
            self::cases(),
            fn (array $labels, self $case): array => [...$labels, $case->value => $case->label()],
            [],
        );
    }
}
