<?php

namespace App\Services;

use App\Models\Member;
use App\Models\MemberRegistration;

interface DavServiceInterface
{
    public function getAddressbookUrl(): string;

    /** @return array<int, array<string, string>> */
    public function getContacts(?string $addressbookUrl = null): array;

    /** @return array<string, mixed>|null */
    public function getContact(string $contactUrl): ?array;

    /** @return array<string, mixed> */
    public function createContactFromMemberRegistration(MemberRegistration $registration): array;

    public function softDeleteMember(Member $member): bool;

    public function restoreMember(Member $member): bool;

    public function deleteMember(Member $member): bool;

    public function updateMember(Member $member): bool;
}
