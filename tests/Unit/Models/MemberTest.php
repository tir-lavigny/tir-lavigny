<?php

namespace Tests\Unit\Models;

use App\MemberStatus;
use App\Models\ActivityLog;
use App\Models\Member;
use App\Models\MemberRegistration;
use App\Support\MemberDataCipher;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class MemberTest extends TestCase
{
    use RefreshDatabase;

    public function test_status_defaults_to_active(): void
    {
        $member = Member::factory()->create();

        $this->assertEquals(MemberStatus::ACTIVE, $member->status);
    }

    public function test_passive_factory_state(): void
    {
        $member = Member::factory()->passive()->create();

        $this->assertEquals(MemberStatus::PASSIVE, $member->status);
    }

    public function test_archived_factory_state(): void
    {
        $member = Member::factory()->archived()->create();

        $this->assertSoftDeleted($member);
    }

    public function test_active_and_passive_scopes(): void
    {
        $active = Member::factory()->create();
        $passive = Member::factory()->passive()->create();

        $this->assertTrue(Member::active()->pluck('id')->contains($active->id));
        $this->assertFalse(Member::active()->pluck('id')->contains($passive->id));
        $this->assertTrue(Member::passive()->pluck('id')->contains($passive->id));
        $this->assertFalse(Member::passive()->pluck('id')->contains($active->id));
    }

    public function test_avs_is_encrypted_at_rest_and_decrypted_on_access(): void
    {
        $member = Member::factory()->create(['avs' => '756.1234.5678.90']);

        $raw = DB::table('members')->where('id', $member->id)->value('avs');

        $this->assertNotSame('756.1234.5678.90', $raw);
        $this->assertSame('756.1234.5678.90', $member->fresh()->avs);
    }

    public function test_legacy_plaintext_avs_does_not_throw_and_is_masked(): void
    {
        $member = Member::factory()->create();

        DB::table('members')->where('id', $member->id)->update(['avs' => '756.0000.0000.00']);

        $fresh = $member->fresh();

        $this->assertNull($fresh->avs);
        $this->assertNull($fresh->avs_masked);
    }

    public function test_avs_hash_is_set_and_deterministic(): void
    {
        $member = Member::factory()->create(['avs' => '756.1234.5678.90']);
        $other = Member::factory()->create(['avs' => '756.12.34.567.890']);

        $cipher = app(MemberDataCipher::class);

        $this->assertSame($cipher->hash('7561234567890'), $member->avs_hash);
        $this->assertSame($member->avs_hash, $other->avs_hash);
    }

    public function test_avs_hash_is_cleared_when_avs_is_removed(): void
    {
        $member = Member::factory()->create(['avs' => '756.1234.5678.90']);

        $member->update(['avs' => null]);

        $this->assertNull($member->fresh()->avs_hash);
    }

    public function test_email_and_avs_are_masked_by_default(): void
    {
        $member = Member::factory()->create([
            'email' => 'john.doe@example.com',
            'avs' => '756.1234.5678.90',
        ]);

        $this->assertSame('j***@example.com', $member->email_masked);
        $this->assertSame('756.***.***.90', $member->avs_masked);
    }

    public function test_sensitive_attributes_are_hidden_from_serialization(): void
    {
        $member = Member::factory()->create([
            'email' => 'john.doe@example.com',
            'avs' => '756.1234.5678.90',
        ]);

        $array = $member->toArray();

        $this->assertArrayNotHasKey('email', $array);
        $this->assertSame('j***@example.com', $array['email_masked']);
        $this->assertArrayNotHasKey('avs', $array);
        $this->assertSame('756.***.***.90', $array['avs_masked']);
    }

    public function test_activity_log_hides_avs_and_masks_email_on_create(): void
    {
        $member = Member::factory()->create([
            'email' => 'john.doe@example.com',
            'avs' => '756.1234.5678.90',
        ]);

        $log = ActivityLog::where('model', Member::class)
            ->where('model_id', (string) $member->id)
            ->where('action', 'created')
            ->sole();

        $changes = json_decode((string) $log->changes, true);

        $this->assertArrayNotHasKey('avs', $changes);
        $this->assertArrayNotHasKey('avs_hash', $changes);
        $this->assertSame('j***@example.com', $changes['email']);
    }

    public function test_activity_log_masks_email_on_update(): void
    {
        $member = Member::factory()->create(['email' => 'john.doe@example.com']);

        $member->update(['email' => 'jane.doe@example.com']);

        $log = ActivityLog::where('model', Member::class)
            ->where('model_id', (string) $member->id)
            ->where('action', 'updated')
            ->sole();

        $changes = json_decode((string) $log->changes, true);

        $this->assertSame('j***@example.com', $changes['email']);
    }

    public function test_registration_activity_log_hides_form_data(): void
    {
        $registration = MemberRegistration::create([
            'form_data' => [
                'first_name' => 'Jean',
                'email' => 'jean@example.com',
                'avs' => '756.1234.5678.90',
            ],
        ]);

        $log = ActivityLog::where('model', MemberRegistration::class)
            ->where('model_id', (string) $registration->id)
            ->where('action', 'created')
            ->sole();

        $changes = json_decode((string) $log->changes, true);

        $this->assertArrayNotHasKey('form_data', $changes);
    }

    public function test_is_anonymized(): void
    {
        $member = Member::factory()->create();
        $anonymized = Member::factory()->create(['anonymized_at' => now()]);

        $this->assertFalse($member->isAnonymized());
        $this->assertTrue($anonymized->isAnonymized());
    }

    public function test_avs_uses_dedicated_member_data_key(): void
    {
        config(['members.encryption_key' => 'base64:'.base64_encode(str_repeat('a', 32))]);

        $member = Member::factory()->create(['avs' => '756.1234.5678.90']);

        $raw = DB::table('members')->where('id', $member->id)->value('avs');

        $this->assertNotSame('756.1234.5678.90', $raw);
        $this->assertSame('756.1234.5678.90', $member->fresh()->avs);
    }

    public function test_dav_synced_snapshot_is_hidden_from_serialization(): void
    {
        $member = Member::factory()->create([
            'dav_synced_snapshot' => ['email' => 'john.doe@example.com'],
        ]);

        $this->assertArrayNotHasKey('dav_synced_snapshot', $member->toArray());
    }
}
