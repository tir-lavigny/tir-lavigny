<?php

namespace Tests\Unit\Models;

use App\MemberRegistrationStatus;
use App\Models\Member;
use App\Models\MemberRegistration;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
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

    public function test_form_url_contains_uuid(): void
    {
        $memberRegistration = MemberRegistration::create();

        $url = $memberRegistration->formUrl();

        $this->assertStringContainsString($memberRegistration->form_uuid, $url);
        $this->assertStringContainsString(config('app.url'), $url);
        $this->assertStringContainsString('/member-registration/', $url);
    }

    public function test_status_defaults_to_pending(): void
    {
        $memberRegistration = MemberRegistration::create();

        $this->assertEquals(MemberRegistrationStatus::PENDING, $memberRegistration->status);
    }

    public function test_form_uuid_is_generated_on_create(): void
    {
        $memberRegistration = MemberRegistration::create();

        $this->assertNotEmpty($memberRegistration->form_uuid);
        $this->assertNotEmpty((string) $memberRegistration->form_uuid);
    }

    public function test_form_data_is_cast_to_array(): void
    {
        $memberRegistration = MemberRegistration::create([
            'form_data' => ['first_name' => 'John', 'last_name' => 'Doe'],
        ]);

        $this->assertIsArray($memberRegistration->form_data);
        $this->assertEquals('John', $memberRegistration->form_data['first_name']);
    }

    public function test_error_message_is_nullable(): void
    {
        $memberRegistration = MemberRegistration::create();

        $this->assertNull($memberRegistration->error_message);
    }

    public function test_can_set_error_message(): void
    {
        $memberRegistration = MemberRegistration::create([
            'error_message' => 'Something went wrong',
        ]);

        $this->assertEquals('Something went wrong', $memberRegistration->error_message);
    }

    public function test_avs_is_encrypted_at_rest_and_decrypted_on_access(): void
    {
        $memberRegistration = MemberRegistration::create([
            'form_data' => ['first_name' => 'Jean', 'avs' => '756.1234.5678.90'],
        ]);

        $raw = DB::table('member_registrations')->where('id', $memberRegistration->id)->value('form_data');
        $decoded = json_decode((string) $raw, true);

        $this->assertNotSame('756.1234.5678.90', $decoded['avs']);
        $this->assertSame('756.1234.5678.90', $memberRegistration->fresh()->form_data['avs']);
    }

    public function test_legacy_plaintext_avs_is_readable(): void
    {
        $memberRegistration = MemberRegistration::create();

        DB::table('member_registrations')->where('id', $memberRegistration->id)->update([
            'form_data' => json_encode(['first_name' => 'Jean', 'avs' => '756.1234.5678.90']),
        ]);

        $this->assertSame('756.1234.5678.90', $memberRegistration->fresh()->form_data['avs']);
    }

    public function test_undecryptable_avs_is_hidden(): void
    {
        $memberRegistration = MemberRegistration::create();

        DB::table('member_registrations')->where('id', $memberRegistration->id)->update([
            'form_data' => json_encode(['avs' => 'not-an-avs']),
        ]);

        $this->assertNull($memberRegistration->fresh()->form_data['avs']);
    }

    public function test_form_data_is_hidden_but_form_name_is_serialized(): void
    {
        $memberRegistration = MemberRegistration::create([
            'form_data' => ['first_name' => 'Jean', 'last_name' => 'Dupont', 'avs' => '756.1234.5678.90'],
        ]);

        $array = $memberRegistration->toArray();

        $this->assertArrayNotHasKey('form_data', $array);
        $this->assertSame('Jean Dupont', $array['form_name']);
    }
}
