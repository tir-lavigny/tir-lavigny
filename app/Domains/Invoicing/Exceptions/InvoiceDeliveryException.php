<?php

namespace App\Domains\Invoicing\Exceptions;

use RuntimeException;

final class InvoiceDeliveryException extends RuntimeException
{
    public static function notDistributable(): self
    {
        return new self('Cette facture ne peut pas être distribuée.');
    }

    public static function documentUnavailable(): self
    {
        return new self('Le PDF archivé de cette facture est introuvable.');
    }

    public static function emailUnavailable(): self
    {
        return new self('Cette facture ne possède pas d’adresse email valide.');
    }

    public static function pendingEmail(): self
    {
        return new self('Un envoi email est déjà en cours pour cette facture.');
    }

    public static function notEmail(): self
    {
        return new self('Cette facture n’est pas configurée pour un envoi par email.');
    }

    public static function notPostal(): self
    {
        return new self('Cette facture n’est pas configurée pour un envoi postal.');
    }

    public static function deliveryAlreadyStarted(): self
    {
        return new self('Le mode de distribution ne peut plus être modifié après le début de la distribution.');
    }

    public static function noInvoices(): self
    {
        return new self('Aucune facture n’a été sélectionnée.');
    }
}
