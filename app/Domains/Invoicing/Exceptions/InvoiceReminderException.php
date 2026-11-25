<?php

namespace App\Domains\Invoicing\Exceptions;

use RuntimeException;

final class InvoiceReminderException extends RuntimeException
{
    public static function notDistributable(): self
    {
        return new self('Cette facture ne peut pas recevoir de rappel.');
    }

    public static function notOverdue(): self
    {
        return new self('Cette facture n’est pas en retard.');
    }

    public static function noOutstandingBalance(): self
    {
        return new self('Cette facture ne présente plus de solde impayé.');
    }

    public static function emailUnavailable(): self
    {
        return new self('Cette facture ne possède pas d’adresse email valide.');
    }

    public static function pendingReminder(): self
    {
        return new self('Un rappel est déjà en cours pour cette facture.');
    }

    public static function printFailed(string $reason): self
    {
        return new self('Le rappel postal n’a pas pu être généré : '.$reason);
    }
}
