<?php

namespace App\Domains\Invoicing\Enums;

enum DeliveryStatus: string
{
    case Pending = 'pending';
    case Sent = 'sent';
    case Failed = 'failed';
    case Printed = 'printed';

    public function label(): string
    {
        return match ($this) {
            self::Pending => 'En attente',
            self::Sent => 'Envoyé',
            self::Failed => 'Échec',
            self::Printed => 'Imprimé',
        };
    }
}
