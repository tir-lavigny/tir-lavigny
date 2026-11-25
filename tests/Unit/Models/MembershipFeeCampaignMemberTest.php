<?php

namespace Tests\Unit\Models;

use App\Domains\Invoicing\Enums\CampaignMemberStatus;
use App\Domains\Invoicing\Models\MembershipFeeCampaign;
use App\Domains\Invoicing\Models\MembershipFeeCampaignMember;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MembershipFeeCampaignMemberTest extends TestCase
{
    use RefreshDatabase;

    public function test_exposes_campaign_amounts_as_decimal_attributes(): void
    {
        $campaignMember = MembershipFeeCampaignMember::factory()->create([
            'base_amount_cents' => 15000,
            'discount_amount_cents' => 3000,
            'final_amount_cents' => 12000,
        ]);

        $this->assertSame('150.00', $campaignMember->base_amount);
        $this->assertSame('30.00', $campaignMember->discount_amount);
        $this->assertSame('120.00', $campaignMember->final_amount);
    }

    public function test_casts_status_to_enum(): void
    {
        $campaignMember = MembershipFeeCampaignMember::factory()->create([
            'status' => CampaignMemberStatus::Validated,
        ]);

        $this->assertSame(CampaignMemberStatus::Validated, $campaignMember->status);
    }

    public function test_exposes_full_exemption_flag(): void
    {
        $full = MembershipFeeCampaignMember::factory()->for(
            MembershipFeeCampaign::factory()->create(['year' => 2027]),
            'campaign',
        )->create([
            'base_amount_cents' => 15000,
            'discount_amount_cents' => 15000,
            'final_amount_cents' => 0,
        ]);
        $partial = MembershipFeeCampaignMember::factory()->for(
            MembershipFeeCampaign::factory()->create(['year' => 2028]),
            'campaign',
        )->create([
            'base_amount_cents' => 15000,
            'discount_amount_cents' => 5000,
            'final_amount_cents' => 10000,
        ]);

        $this->assertTrue($full->is_full_exemption);
        $this->assertFalse($partial->is_full_exemption);
    }
}
