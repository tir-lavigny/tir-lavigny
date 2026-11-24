<?php

namespace App\Domains\Invoicing\Enums;

enum FinancialDocumentStatus: string
{
    case Pending = 'pending';
    case Stored = 'stored';
    case Failed = 'failed';

    public function label(): string
    {
        return match ($this) {
            self::Pending => 'En préparation',
            self::Stored => 'Archivé',
            self::Failed => 'Échec de l’archivage',
        };
    }
}
