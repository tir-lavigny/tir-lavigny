<?php

namespace App\Domains\Invoicing\Services;

use App\Domains\Invoicing\Enums\InvoiceDeliveryMethod;
use App\Domains\Invoicing\Models\MembershipFeeCampaignMember;

class MembershipFeeCampaignMemberValidator
{
    /**
     * @return array<int, string>
     */
    public function errors(MembershipFeeCampaignMember $campaignMember): array
    {
        $campaignMember->loadMissing('member', 'billingCustomer');

        $errors = [];
        $member = $campaignMember->member;

        if ($member === null) {
            return ['Le membre est introuvable.'];
        }

        if ($member->isAnonymized()) {
            $errors[] = 'Le membre est anonymisé.';
        }

        if ($campaignMember->base_amount_cents <= 0) {
            $errors[] = 'Le montant de la cotisation doit être positif.';
        }

        if ($campaignMember->discount_amount_cents < 0) {
            $errors[] = 'La remise ne peut pas être négative.';
        }

        if ($campaignMember->discount_amount_cents > $campaignMember->base_amount_cents) {
            $errors[] = 'La remise dépasse le montant de la cotisation.';
        }

        if ($campaignMember->hasFullExemption() && blank($campaignMember->discount_reason)) {
            $errors[] = 'Un motif est obligatoire pour une exonération totale.';
        }

        $billingCustomer = $campaignMember->billingCustomer;

        if ($billingCustomer === null) {
            $errors[] = 'Le client de facturation est introuvable.';
        } else {
            if (blank($billingCustomer->address)) {
                $errors[] = 'L’adresse de facturation est manquante.';
            }

            if (blank($billingCustomer->postal_code)) {
                $errors[] = 'Le code postal de facturation est manquant.';
            }

            if (blank($billingCustomer->city)) {
                $errors[] = 'La localité de facturation est manquante.';
            }

            if (blank($billingCustomer->country)) {
                $errors[] = 'Le pays de facturation est manquant.';
            }

            if ($campaignMember->delivery_method === InvoiceDeliveryMethod::Email) {
                if (blank($billingCustomer->email) || filter_var($billingCustomer->email, FILTER_VALIDATE_EMAIL) === false) {
                    $errors[] = 'Une adresse email valide est obligatoire pour l’envoi par email.';
                }
            }
        }

        return $errors;
    }

    public function isValid(MembershipFeeCampaignMember $campaignMember): bool
    {
        return $this->errors($campaignMember) === [];
    }
}
