<?php

namespace Tests\Unit\Services;

use App\MemberStatus;
use App\Models\Member;
use App\Services\InfomaniakDavService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use ReflectionClass;
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
