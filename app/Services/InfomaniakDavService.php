<?php

namespace App\Services;

use Illuminate\Support\Str;
use Sabre\DAV\Client;
use Sabre\VObject\Reader;
use Sabre\Xml\Reader as XmlReader;

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
                            logger()->warning('Failed to parse vCard', [
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

    public function createContact(array $data, ?string $addressbookUrl = null): array
    {
        $url = $addressbookUrl ?? $this->getAddressbookUrl();

        $uid = Str::uuid7().'@tir-lavigny.ch';
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
