<?php

namespace Tests\Unit\Services;

use App\Domains\Invoicing\Exceptions\MembershipFeeException;
use App\Domains\Invoicing\Models\MembershipFeeType;
use App\Domains\Invoicing\Services\MembershipFeeResolver;
use App\MemberStatus;
use App\Models\Member;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MembershipFeeResolverTest extends TestCase
{
    use RefreshDatabase;

    public function test_resolves_the_fee_type_for_the_member_status(): void
    {
        $active = MembershipFeeType::factory()->create([
            'member_status' => MemberStatus::ACTIVE,
            'amount_cents' => 15000,
        ]);
        $passive = MembershipFeeType::factory()->passive()->create([
            'amount_cents' => 5000,
        ]);
        $resolver = app(MembershipFeeResolver::class);

        $this->assertSame($active->id, $resolver->resolve(Member::factory()->create())->id);
        $this->assertSame($passive->id, $resolver->resolve(Member::factory()->passive()->create())->id);
    }

    public function test_rejects_a_member_status_without_a_fee_type(): void
    {
        $this->expectException(MembershipFeeException::class);
        $this->expectExceptionMessage('Aucun type de cotisation n’est configuré');

        app(MembershipFeeResolver::class)->resolve(Member::factory()->create());
    }
}
