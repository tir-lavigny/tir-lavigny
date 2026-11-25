<?php

namespace App\Domains\Invoicing\Exceptions;

use RuntimeException;

final class PaymentException extends RuntimeException
{
    public static function invalidAmount(): self
    {
        return new self('Le montant doit être positif.');
    }

    public static function invoiceNotPayable(): self
    {
        return new self('Cette facture ne peut pas recevoir de paiement.');
    }

    public static function amountExceedsPayment(): self
    {
        return new self('Le montant dépasse le solde non alloué du paiement.');
    }

    public static function amountExceedsInvoice(): self
    {
        return new self('Le montant dépasse le reste à payer de la facture.');
    }

    public static function idempotencyConflict(): self
    {
        return new self('Cette demande a déjà été traitée avec des données différentes.');
    }

    public static function paymentVoided(): self
    {
        return new self('Ce paiement est annulé et ne peut plus être utilisé.');
    }

    public static function allocationAlreadyReversed(): self
    {
        return new self('Cette imputation est déjà annulée.');
    }

    public static function reasonRequired(): self
    {
        return new self('Un motif est obligatoire.');
    }

    public static function voidWithActiveAllocations(): self
    {
        return new self('Retirez d’abord les imputations actives de ce paiement.');
    }

    public static function editAmountWithActiveAllocations(): self
    {
        return new self('Retirez d’abord les imputations actives avant de modifier le montant.');
    }

    public static function paymentNotEditable(): self
    {
        return new self('Un paiement annulé ne peut plus être modifié.');
    }
}
