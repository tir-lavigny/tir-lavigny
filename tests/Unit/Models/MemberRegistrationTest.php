<?php

namespace Tests\Unit\Models;

use App\MemberRegistrationStatus;
use App\Models\Member;
use App\Models\MemberRegistration;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MemberRegistrationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
    }

    public function test_can_create_member_registration(): void
    {
        $memberRegistration = MemberRegistration::create();

        $this->assertDatabaseHas('member_registrations', [
            'id' => $memberRegistration->id,
            'form_uuid' => $memberRegistration->form_uuid,
            'status' => MemberRegistrationStatus::PENDING->value,
            'dav_uid' => null,
            'member_id' => null,
            'form_data' => null,
            'error_message' => null,
        ]);
    }

    public function test_can_associate_member_with_registration(): void
    {
        $member = Member::factory()->create();
        $memberRegistration = MemberRegistration::create();

        $memberRegistration->member()->associate($member);
        $memberRegistration->save();

        $this->assertDatabaseHas('member_registrations', [
            'id' => $memberRegistration->id,
            'member_id' => $member->id,
        ]);

        $this->assertEquals($member->id, $memberRegistration->member->id);
        $this->assertEquals($memberRegistration->id, $member->registration->id);
    }
}
