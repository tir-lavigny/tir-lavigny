<?php

namespace App\Services;

use App\Models\Member;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class StubDavService implements DavServiceInterface
{
    public function getAddressbookUrl(): string
    {
        return 'https://stub.dav.example.com/addressbook/';
    }

    /** @return array<int, array<string, string>> */
    public function getContacts(?string $addressbookUrl = null): array
    {
        Log::info('StubDavService: getContacts called');

        return [];
    }

    /** @return array<string, string>|null */
    public function getContact(string $contactUrl): ?array
    {
        Log::info('StubDavService: getContact called', ['url' => $contactUrl]);

        return null;
    }

    /** @return array<string, string> */
    public function createContactFromMember(Member $member): array
    {
        $uid = Str::uuid7();

        Log::info('StubDavService: createContactFromMember called', [
            'member_id' => $member->id,
            'uid' => $uid,
        ]);

        return [
            'url' => $this->getAddressbookUrl().$uid.'.vcf',
            'uid' => $uid,
            'fn' => $member->last_name.' '.$member->first_name,
            'vcard_raw' => 'BEGIN:VCARD'.$uid.'END:VCARD',
        ];
    }

    public function deleteMember(Member $member): bool
    {
        Log::info('StubDavService: deleteMember called', [
            'member_id' => $member->id,
            'dav_uid' => $member->dav_uid,
        ]);

        return true;
    }

    public function updateMember(Member $member): bool
    {
        Log::info('StubDavService: updateMember called', [
            'member_id' => $member->id,
            'dav_uid' => $member->dav_uid,
        ]);

        return true;
    }
}
