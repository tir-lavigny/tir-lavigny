<?php

namespace App\Services;

use App\DavGroupLabels as GroupLabels;
use App\Models\Member;
use App\Models\MemberRegistration;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Sabre\DAV\Client;
use Sabre\VObject\Component;
use Sabre\VObject\Reader;
use Sabre\Xml\Reader as XmlReader;

class InfomaniakDavService implements DavServiceInterface
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

    /** @return array<int, array<string, string>> */
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
                                'adr' => $this->extractProperty($vcard, 'ADR'),
                                'category' => $this->extractProperty($vcard, 'CATEGORIES'),
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

    /** @return array<string, string>|null */
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
                'adr' => $this->extractProperty($vcard, 'ADR'),
                'category' => $this->extractProperty($vcard, 'CATEGORIES'),
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

    /** @param array<string, string> $data
     * @return array<string, string> */
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

        if ($address = $this->buildAddress($data)) {
            $vcard .= 'ADR;TYPE=HOME:;;'.$address."\r\n";
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

    /** @param array<string, string> $data */
    private function updateContact(string $contactUrl, array $data): bool
    {
        $existingContact = $this->getContact($contactUrl);

        if (! $existingContact) {
            throw new \RuntimeException('Contact not found for update: '.$contactUrl);
        }

        $vcard = Reader::read(trim($existingContact['vcard_raw']));

        if (isset($data['fn'])) {
            $vcard->remove('FN');
            $vcard->add('FN', $data['fn']);
        }

        if (isset($data['n_first'], $data['n_last'])) {
            $vcard->remove('N');
            $vcard->add('N', [$data['n_last'], $data['n_first'], '', '', '']);
        }

        $this->replaceTextProperty($vcard, 'EMAIL', $data['email'] ?? null, ['TYPE' => 'INTERNET']);
        $this->replaceTextProperty($vcard, 'TEL', $data['tel'] ?? null, ['TYPE' => 'CELL']);
        $this->replaceTextProperty($vcard, 'CATEGORIES', $data['category'] ?? null);

        if ($address = $this->buildAddress($data)) {
            $vcard->remove('ADR');
            $vcard->add('ADR', ['', '', $data['street'] ?? '', $data['city'] ?? '', $data['canton'] ?? '', $data['npa'] ?? '', $data['country'] ?? ''], ['TYPE' => 'HOME']);
        } else {
            $vcard->remove('ADR');
        }

        $response = $this->client->request('PUT', $contactUrl, $vcard->serialize(), [
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

    /** @return array<string, string> */
    public function createContactFromMemberRegistration(MemberRegistration $registration): array
    {
        $formData = $registration->form_data;

        return $this->createContact([
            'fn' => $formData['last_name'].' '.$formData['first_name'],
            'n_first' => $formData['first_name'],
            'n_last' => $formData['last_name'],
            'email' => $formData['email'] ?? '',
            'tel' => $formData['tel'] ?? '',
            'street' => $formData['street'] ?? '',
            'npa' => $formData['npa'] ?? '',
            'city' => $formData['city'] ?? '',
            'canton' => $formData['canton'] ?? '',
            'country' => $formData['country'] ?? '',
            'category' => GroupLabels::ACTIVE_MEMBERS->value,
        ]);
    }

    /** @return array<string, mixed> */
    public function createContactFromMember(Member $member): array
    {
        return $this->createContact($this->contactDataFromMember($member));
    }

    /** @return array<string, string> */
    private function contactDataFromMember(Member $member): array
    {
        return [
            'fn' => $member->last_name.' '.$member->first_name,
            'n_first' => $member->first_name,
            'n_last' => $member->last_name,
            'email' => $member->email ?? '',
            'tel' => $member->tel ?? '',
            'street' => $member->street ?? '',
            'npa' => $member->npa ?? '',
            'city' => $member->city ?? '',
            'canton' => $member->canton ?? '',
            'country' => $member->country ?? '',
            'category' => $this->categoryForMember($member),
        ];
    }

    private function categoryForMember(Member $member): string
    {
        if ($member->trashed()) {
            return GroupLabels::SOFT_DELETED_MEMBERS->value;
        }

        return $member->status->davGroup()->value;
    }

    /** @param array<string, string> $data */
    private function buildAddress(array $data): string
    {
        return implode(';', [
            $this->escapeVcardValue($data['street'] ?? ''),
            $this->escapeVcardValue($data['city'] ?? ''),
            $this->escapeVcardValue($data['canton'] ?? ''),
            $this->escapeVcardValue($data['npa'] ?? ''),
            $this->escapeVcardValue($data['country'] ?? ''),
        ]);
    }

    /** @param array<string, string> $parameters */
    private function replaceTextProperty(Component $vcard, string $name, ?string $value, array $parameters = []): void
    {
        $vcard->remove($name);

        if ($value !== null && $value !== '') {
            $vcard->add($name, $value, $parameters);
        }
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
            $this->updateContact($contactUrl, $this->contactDataFromMember($member));
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
            $this->updateContact($contactUrl, $this->contactDataFromMember($member));
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
            $this->updateContact($contactUrl, $this->contactDataFromMember($member));
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

    /** @param Component $vcard */
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
