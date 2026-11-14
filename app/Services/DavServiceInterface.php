<?php

namespace App\Services;

use App\Models\Member;

interface DavServiceInterface
{
    public function getAddressbookUrl(): string;

    /** @return array<int, array<string, string>> */
    public function getContacts(?string $addressbookUrl = null): array;

    /** @return array<string, mixed>|null */
    public function getContact(string $contactUrl): ?array;

    /** @return array<string, mixed> */
    public function createContactFromMember(Member $member): array;

    public function deleteMember(Member $member): bool;

    public function updateMember(Member $member): bool;
}
