<?php

namespace Tests\Unit\Models;

use App\Domains\Invoicing\Models\MembershipFeeCampaignActivityLog;
use Illuminate\Foundation\Testing\RefreshDatabase;
use LogicException;
use Tests\TestCase;

class MembershipFeeCampaignActivityLogTest extends TestCase
{
    use RefreshDatabase;

    public function test_activity_log_cannot_be_updated(): void
    {
        $log = MembershipFeeCampaignActivityLog::factory()->create();

        $this->expectException(LogicException::class);

        $log->update(['action' => 'campaign.changed']);
    }

    public function test_activity_log_cannot_be_deleted(): void
    {
        $log = MembershipFeeCampaignActivityLog::factory()->create();

        $this->expectException(LogicException::class);

        $log->delete();
    }
}
