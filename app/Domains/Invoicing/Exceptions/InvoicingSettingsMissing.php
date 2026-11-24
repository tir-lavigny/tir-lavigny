<?php

namespace App\Domains\Invoicing\Exceptions;

use RuntimeException;

class InvoicingSettingsMissing extends RuntimeException
{
    public static function make(): self
    {
        return new self('Les paramètres de facturation doivent être configurés avant de créer ou d’émettre une facture.');
    }
}
