<?php

namespace App\Services;

use App\Models\Member;
use App\Models\MemberRegistration;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Sabre\DAV\Client;
use Sabre\VObject\Reader;
use Sabre\Xml\Reader as XmlReader;

enum GroupLabels: string
{
    case ACTIVE_MEMBERS = 'Actifs';
    case SOFT_DELETED_MEMBERS = 'Archives';
}

class InfomaniakDavService
{
    private Client $client;

    public function __construct()
    {
        $this->client = new Client([
            'baseUri' => config('dav.base_url'),
            'userName' => config('dav.username'),
            'password' => config('dav.password'),
        ]);
    }

    public function getAddressbookUrl(): string
    {
        return config('dav.addressbook_url');
    }

    public function getContacts(?string $addressbookUrl = null): array
    {
        $url = $addressbookUrl ?? $this->getAddressbookUrl();

        $xml = '<?xml version="1.0" encoding="utf-8" ?>
            <card:addressbook-query xmlns:d="DAV:" xmlns:card="urn:ietf:params:xml:ns:carddav">
                <d:prop>
                    <d:getetag />
                    <card:address-data />
                </d:prop>
            </card:addressbook-query>';

        $response = $this->client->request('REPORT', $url, $xml, [
            'Depth' => '1',
            'Content-Type' => 'application/xml; charset=utf-8',
        ]);

        if ($response['statusCode'] >= 400) {
            throw new \RuntimeException(sprintf(
                'DAV REPORT failed with status %d: %s',
                $response['statusCode'],
                $response['body'],
            ));
        }

        $body = $response['body'];

        if (! $body) {
            return [];
        }

        $reader = new XmlReader;
        $reader->xml($body);

        $contacts = [];
        $currentHref = null;
        $currentEtag = null;
        $currentVcard = null;
        $inHref = false;
        $inEtag = false;
        $inAddressData = false;
        $hrefDepth = 0;
        $etagDepth = 0;
        $addressDataDepth = 0;

        while ($reader->read()) {
            $node = $reader->localName;
            $namespace = $reader->namespaceURI;

            if ($reader->nodeType === \XMLReader::ELEMENT) {
                if ($node === 'href' && $namespace === 'DAV:') {
                    $inHref = true;
                    $hrefDepth = $reader->depth;
                }
                if ($node === 'getetag' && $namespace === 'DAV:') {
                    $inEtag = true;
                    $etagDepth = $reader->depth;
                }
                if ($node === 'address-data' && $namespace === 'urn:ietf:params:xml:ns:carddav') {
                    $inAddressData = true;
                    $addressDataDepth = $reader->depth;
                    $currentVcard = '';
                }
            }

            if ($reader->nodeType === \XMLReader::TEXT || $reader->nodeType === \XMLReader::CDATA) {
                if ($inHref) {
                    $currentHref = $reader->value;
                    $inHref = false;
                }
                if ($inEtag) {
                    $currentEtag = $reader->value;
                    $inEtag = false;
                }
                if ($inAddressData) {
                    $currentVcard .= $reader->value;
                }
            }

            if ($reader->nodeType === \XMLReader::END_ELEMENT) {
                if ($inAddressData && $reader->depth === $addressDataDepth) {
                    $inAddressData = false;
                }

                if ($node === 'response' && $namespace === 'DAV:') {
                    if ($currentHref && $currentVcard) {
                        try {
                            $vcard = Reader::read(trim($currentVcard));
                            $contacts[] = [
                                'url' => $currentHref,
                                'etag' => $currentEtag,
                                'uid' => (string) ($vcard->UID ?? ''),
                                'fn' => (string) ($vcard->FN ?? ''),
                                'n' => (string) ($vcard->N ?? ''),
                                'email' => $this->extractProperty($vcard, 'EMAIL'),
                                'tel' => $this->extractProperty($vcard, 'TEL'),
                                'org' => (string) ($vcard->ORG ?? ''),
                                'vcard_raw' => $currentVcard,
                            ];
                        } catch (\Exception $e) {
                            Log::warning('Failed to parse vCard', [
                                'href' => $currentHref,
                                'error' => $e->getMessage(),
                            ]);
                        }
                    }
                    $currentHref = null;
                    $currentEtag = null;
                    $currentVcard = null;
                }
            }
        }

        return $contacts;
    }

    public function getContact(string $contactUrl): ?array
    {
        $response = $this->client->request('GET', $contactUrl);

        if ($response['statusCode'] >= 400) {
            Log::error('Failed to get contact from Infomaniak DAV', [
                'contact_url' => $contactUrl,
                'status_code' => $response['statusCode'],
                'response_body' => $response['body'],
            ]);
            throw new \RuntimeException(sprintf(
                'DAV GET failed with status %d: %s',
                $response['statusCode'],
                $response['body'],
            ));
        }

        $vcardRaw = $response['body'];

        if (! $vcardRaw) {
            return null;
        }

        try {
            $vcard = Reader::read(trim($vcardRaw));

            return [
                'url' => $contactUrl,
                'uid' => (string) ($vcard->UID ?? ''),
                'fn' => (string) ($vcard->FN ?? ''),
                'n' => (string) ($vcard->N ?? ''),
                'email' => $this->extractProperty($vcard, 'EMAIL'),
                'tel' => $this->extractProperty($vcard, 'TEL'),
                'org' => (string) ($vcard->ORG ?? ''),
                'vcard_raw' => $vcardRaw,
            ];
        } catch (\Exception $e) {
            Log::warning('Failed to parse vCard', [
                'url' => $contactUrl,
                'error' => $e->getMessage(),
            ]);

            return null;
        }
    }

    private function createContact(array $data, ?string $addressbookUrl = null): array
    {
        $url = $addressbookUrl ?? $this->getAddressbookUrl();

        $uid = Str::uuid7();
        $filename = $uid.'.vcf';

        $vcard = "BEGIN:VCARD\r\n";
        $vcard .= "VERSION:3.0\r\n";
        $vcard .= "UID:{$uid}\r\n";
        $vcard .= 'FN:'.$this->escapeVcardValue($data['fn'])."\r\n";
        $vcard .= 'N:'.$this->escapeVcardValue($data['n_last']).';'.$this->escapeVcardValue($data['n_first']).";;;\r\n";

        if (! empty($data['email'])) {
            $vcard .= 'EMAIL;TYPE=INTERNET:'.$this->escapeVcardValue($data['email'])."\r\n";
        }

        if (! empty($data['tel'])) {
            $vcard .= 'TEL;TYPE=CELL:'.$this->escapeVcardValue($data['tel'])."\r\n";
        }

        if (! empty($data['category'])) {
            $vcard .= 'CATEGORIES:'.$this->escapeVcardValue($data['category'])."\r\n";
        }

        $vcard .= "END:VCARD\r\n";

        $putUrl = rtrim($url, '/').'/'.$filename;

        $response = $this->client->request('PUT', $putUrl, $vcard, [
            'Content-Type' => 'text/vcard; charset=utf-8',
        ]);

        if ($response['statusCode'] >= 400) {
            Log::error('Failed to create contact in Infomaniak DAV', [
                'put_url' => $putUrl,
                'status_code' => $response['statusCode'],
                'response_body' => $response['body'],
            ]);
            throw new \RuntimeException(sprintf(
                'DAV PUT failed with status %d: %s',
                $response['statusCode'],
                $response['body'],
            ));
        }

        return [
            'url' => $putUrl,
            'uid' => $uid,
            'fn' => $data['fn'],
            'vcard_raw' => $vcard,
        ];
    }

    private function deleteContact(string $contactUrl): bool
    {
        $response = $this->client->request('DELETE', $contactUrl);

        if ($response['statusCode'] >= 400) {
            Log::error('Failed to delete contact from Infomaniak DAV', [
                'contact_url' => $contactUrl,
                'status_code' => $response['statusCode'],
                'response_body' => $response['body'],
            ]);
            throw new \RuntimeException(sprintf(
                'DAV DELETE failed with status %d: %s',
                $response['statusCode'],
                $response['body'],
            ));
        }

        return true;
    }

    private function updateContact(string $contactUrl, array $data): bool
    {
        $existingContact = $this->getContact($contactUrl);

        if (! $existingContact) {
            throw new \RuntimeException('Contact not found for update: '.$contactUrl);
        }

        $vcard = "BEGIN:VCARD\r\n";
        $vcard .= "VERSION:3.0\r\n";
        $vcard .= 'UID:'.$existingContact['uid']."\r\n";
        $vcard .= 'FN:'.$this->escapeVcardValue($data['fn'])."\r\n";
        $vcard .= 'N:'.$this->escapeVcardValue($data['n_last']).';'.$this->escapeVcardValue($data['n_first']).";;;\r\n";

        if (! empty($data['email'])) {
            $vcard .= 'EMAIL;TYPE=INTERNET:'.$this->escapeVcardValue($data['email'])."\r\n";
        }

        if (! empty($data['tel'])) {
            $vcard .= 'TEL;TYPE=CELL:'.$this->escapeVcardValue($data['tel'])."\r\n";
        }

        if (! empty($data['category'])) {
            $vcard .= 'CATEGORIES:'.$this->escapeVcardValue($data['category'])."\r\n";
        }

        $vcard .= "END:VCARD\r\n";

        $response = $this->client->request('PUT', $contactUrl, $vcard, [
            'Content-Type' => 'text/vcard; charset=utf-8',
        ]);

        if ($response['statusCode'] >= 400) {
            throw new \RuntimeException(sprintf(
                'DAV PUT failed with status %d: %s',
                $response['statusCode'],
                $response['body'],
            ));
        }

        return true;
    }

    public function createContactFromMemberRegistration(MemberRegistration $registration): array
    {
        return $this->createContact([
            'fn' => $registration->form_data['last_name'].' '.$registration->form_data['first_name'],
            'n_first' => $registration->form_data['first_name'],
            'n_last' => $registration->form_data['last_name'],
            'email' => $registration->form_data['email'],
            'tel' => $registration->form_data['tel'] ?? '',
            'category' => GroupLabels::ACTIVE_MEMBERS->value,
        ]);
    }

    public function softDeleteMember(Member $member): bool
    {
        if (! $member->dav_uid) {
            return true; // No DAV UID, nothing to delete
        }

        $contactUrl = rtrim($this->getAddressbookUrl(), '/').'/'.$member->dav_uid.'.vcf';

        Log::info('Soft deleting contact in Infomaniak DAV', [
            'member_id' => $member->id,
            'dav_uid' => $member->dav_uid,
            'contact_url' => $contactUrl,
        ]);

        try {
            $this->updateContact($contactUrl, [
                'fn' => $member->first_name.' '.$member->last_name,
                'n_first' => $member->first_name,
                'n_last' => $member->last_name,
                'category' => GroupLabels::SOFT_DELETED_MEMBERS->value,
            ]);
        } catch (\Exception $e) {
            Log::warning('Failed to soft delete contact in Infomaniak DAV', [
                'member_id' => $member->id,
                'dav_uid' => $member->dav_uid,
                'error' => $e->getMessage(),
            ]);

            return false;
        }

        return true;
    }

    public function restoreMember(Member $member): bool
    {
        if (! $member->dav_uid) {
            return true; // No DAV UID, nothing to restore
        }

        $contactUrl = rtrim($this->getAddressbookUrl(), '/').'/'.$member->dav_uid.'.vcf';

        try {
            $this->updateContact($contactUrl, [
                'fn' => $member->first_name.' '.$member->last_name,
                'n_first' => $member->first_name,
                'n_last' => $member->last_name,
                'category' => GroupLabels::ACTIVE_MEMBERS->value,
            ]);
        } catch (\Exception $e) {
            Log::warning('Failed to restore contact in Infomaniak DAV', [
                'member_id' => $member->id,
                'dav_uid' => $member->dav_uid,
                'error' => $e->getMessage(),
            ]);

            return false;
        }

        return true;
    }

    public function deleteMember(Member $member): bool
    {

        if (! $member->dav_uid) {
            return true; // No DAV UID, nothing to delete
        }

        $contactUrl = rtrim($this->getAddressbookUrl(), '/').'/'.$member->dav_uid.'.vcf';

        try {
            $this->deleteContact($contactUrl);
        } catch (\Exception $e) {
            Log::warning('Failed to delete contact from Infomaniak DAV', [
                'member_id' => $member->id,
                'dav_uid' => $member->dav_uid,
                'error' => $e->getMessage(),
            ]);

            return false;
        }

        return true;
    }

    public function updateMember(Member $member): bool
    {
        if (! $member->dav_uid) {
            return false; // No DAV UID, cannot update
        }

        $contactUrl = rtrim($this->getAddressbookUrl(), '/').'/'.$member->dav_uid.'.vcf';

        try {
            $this->updateContact($contactUrl, [
                'fn' => $member->first_name.' '.$member->last_name,
                'n_first' => $member->first_name,
                'n_last' => $member->last_name,
            ]);
        } catch (\Exception $e) {
            Log::warning('Failed to update contact in Infomaniak DAV', [
                'member_id' => $member->id,
                'dav_uid' => $member->dav_uid,
                'error' => $e->getMessage(),
            ]);

            return false;
        }

        return true;
    }

    private function escapeVcardValue(string $value): string
    {
        return str_replace(
            ['\\', ',', ';', "\n"],
            ['\\\\', '\\,', '\\;', '\\n'],
            $value,
        );
    }

    private function extractProperty($vcard, string $propertyName): string
    {
        $values = [];

        if (isset($vcard->$propertyName)) {
            foreach ($vcard->$propertyName as $property) {
                $values[] = (string) $property;
            }
        }

        return implode(', ', $values);
    }
}
