<?php

namespace App\Domains\Invoicing\Exceptions;

use App\MemberStatus;
use RuntimeException;

final class MembershipFeeException extends RuntimeException
{
    public static function feeTypeNotConfigured(MemberStatus $status): self
    {
        return new self("Aucun type de cotisation n’est configuré pour le statut « {$status->label()} ».");
    }

    public static function campaignNotEditable(): self
    {
        return new self('Cette campagne ne peut plus être modifiée.');
    }

    public static function campaignMemberNotEditable(): self
    {
        return new self('Ce membre de campagne ne peut plus être modifié.');
    }

    public static function campaignMemberAlreadyInvoiced(): self
    {
        return new self('Une facture existe déjà pour ce membre.');
    }

    public static function discountExceedsBase(): self
    {
        return new self('La remise ne peut pas dépasser le montant de la cotisation.');
    }

    public static function discountCannotBeNegative(): self
    {
        return new self('La remise ne peut pas être négative.');
    }

    public static function discountReasonRequired(): self
    {
        return new self('Un motif est obligatoire pour une exonération totale.');
    }

    public static function campaignNotValidated(): self
    {
        return new self('La campagne doit être validée avant de générer les factures.');
    }

    public static function campaignHasPendingMembers(): self
    {
        return new self('Tous les membres inclus doivent être validés avant de générer les factures.');
    }

    public static function campaignHasNoMembers(): self
    {
        return new self('La campagne ne contient aucun membre.');
    }

    public static function campaignHasNoIncludedMembers(): self
    {
        return new self('La campagne doit contenir au moins un membre inclus.');
    }

    public static function campaignHasNoDraftInvoices(): self
    {
        return new self('Aucune facture brouillon n’est en attente d’émission.');
    }

    public static function campaignHasInvoices(): self
    {
        return new self('Cette campagne contient des factures. Supprimez d’abord les factures brouillon.');
    }

    public static function campaignHasNonDraftInvoices(): self
    {
        return new self('Impossible de supprimer la campagne : elle contient au moins une facture émise.');
    }

    /**
     * @param  array<int, string>  $members
     */
    public static function baseAmountRefreshConflicts(array $members): self
    {
        return new self(
            'Impossible de recalculer les montants : la remise dépasse le nouveau montant de base pour '
            .implode(', ', $members).'.',
        );
    }

    public static function deliveryRequiresEmail(): self
    {
        return new self('Ce membre n’a pas d’adresse email. Choisissez l’envoi postal.');
    }

    public static function memberCannotBeInvoiced(string $reason): self
    {
        return new self("Ce membre ne peut pas être facturé : {$reason}");
    }
}
