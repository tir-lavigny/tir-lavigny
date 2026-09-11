<?php

namespace App\Services;

use App\Models\Member;
use App\Models\MemberRegistration;

interface DavServiceInterface
{
    public function getAddressbookUrl(): string;

    public function getContacts(?string $addressbookUrl = null): array;

    public function getContact(string $contactUrl): ?array;

    public function createContactFromMemberRegistration(MemberRegistration $registration): array;

    public function softDeleteMember(Member $member): bool;

    public function restoreMember(Member $member): bool;

    public function deleteMember(Member $member): bool;

    public function updateMember(Member $member): bool;
}
