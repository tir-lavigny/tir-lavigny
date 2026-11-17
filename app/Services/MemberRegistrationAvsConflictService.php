<?php

namespace App\Services;

use App\Models\Member;
use App\Models\MemberRegistration;
use App\Rules\UniqueMemberAvs;

class MemberRegistrationAvsConflictService
{
    public function find(MemberRegistration $registration): ?Member
    {
        $formData = $registration->form_data;

        if (! is_array($formData)) {
            return null;
        }

        return UniqueMemberAvs::findMember($formData['avs'] ?? null, $registration->member_id);
    }
}
