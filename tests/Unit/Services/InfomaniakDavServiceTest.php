<?php

namespace Tests\Unit\Services;

use App\MemberStatus;
use App\Models\Member;
use App\Services\InfomaniakDavService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use ReflectionClass;
use Sabre\VObject\Reader;
use Tests\TestCase;

class InfomaniakDavServiceTest extends TestCase
{
    use RefreshDatabase;

    private function callBuildAddress(array $data): string
    {
        $service = new InfomaniakDavService;
        $reflection = new ReflectionClass($service);
        $method = $reflection->getMethod('buildAddress');
        $method->setAccessible(true);

        return $method->invoke($service, $data);
    }

    private function callCategoryForMember(Member $member): string
    {
        $service = new InfomaniakDavService;
        $reflection = new ReflectionClass($service);
        $method = $reflection->getMethod('categoryForMember');
        $method->setAccessible(true);

        return $method->invoke($service, $member);
    }

    private function callExtractFirstProperty(string $vcard, string $property): string
    {
        $service = new InfomaniakDavService;
        $reflection = new ReflectionClass($service);
        $method = $reflection->getMethod('extractFirstProperty');
        $method->setAccessible(true);

        return $method->invoke($service, Reader::read(trim($vcard)), $property);
    }

    public function test_contact_with_multiple_emails_uses_first(): void
    {
        $vcard = "BEGIN:VCARD\r\nVERSION:3.0\r\nUID:1\r\nFN:Jean Dupont\r\nN:Dupont;Jean;;;\r\nEMAIL:first@example.com\r\nEMAIL:second@example.com\r\nEND:VCARD\r\n";

        $this->assertSame('first@example.com', $this->callExtractFirstProperty($vcard, 'EMAIL'));
    }

    public function test_contact_with_multiple_tels_uses_first(): void
    {
        $vcard = "BEGIN:VCARD\r\nVERSION:3.0\r\nUID:1\r\nFN:Jean Dupont\r\nN:Dupont;Jean;;;\r\nTEL:+41 79 000 00 00\r\nTEL:+41 79 111 11 11\r\nEND:VCARD\r\n";

        $this->assertSame('+41 79 000 00 00', $this->callExtractFirstProperty($vcard, 'TEL'));
    }

    public function test_build_address_returns_empty_string_without_address(): void
    {
        $this->assertSame('', $this->callBuildAddress([]));
    }

    public function test_build_address_includes_components(): void
    {
        $address = $this->callBuildAddress([
            'street' => 'Rue du Marché 1',
            'npa' => '1003',
            'city' => 'Lausanne',
            'canton' => 'VD',
            'country' => 'Suisse',
        ]);

        $this->assertSame('Rue du Marché 1;Lausanne;VD;1003;Suisse', $address);
    }

    public function test_category_for_active_member(): void
    {
        $member = Member::factory()->create(['status' => MemberStatus::ACTIVE]);

        $this->assertSame('Actifs', $this->callCategoryForMember($member));
    }

    public function test_category_for_passive_member(): void
    {
        $member = Member::factory()->passive()->create();

        $this->assertSame('Passifs', $this->callCategoryForMember($member));
    }

    public function test_category_for_archived_member(): void
    {
        $member = Member::factory()->archived()->create();

        $this->assertSame('Archives', $this->callCategoryForMember($member));
    }
}
