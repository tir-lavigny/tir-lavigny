<?php

namespace Tests\Feature\Console;

use App\Models\Member;
use App\Models\MemberRegistration;
use App\Support\MemberDataCipher;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class ReencryptMemberAvsTest extends TestCase
{
    use RefreshDatabase;

    private string $oldKey;

    private string $newKey;

    protected function setUp(): void
    {
        parent::setUp();

        $this->oldKey = 'base64:'.base64_encode(str_repeat('a', 32));
        $this->newKey = 'base64:'.base64_encode(str_repeat('b', 32));
    }

    public function test_reencrypts_member_and_registration_avs(): void
    {
        $oldCipher = new MemberDataCipher($this->oldKey);
        $newCipher = new MemberDataCipher($this->newKey);

        $member = Member::factory()->create();
        DB::table('members')->where('id', $member->id)->update([
            'avs' => $oldCipher->encrypt('756.1234.5678.90'),
            'avs_hash' => null,
        ]);

        $registration = MemberRegistration::create();
        DB::table('member_registrations')->where('id', $registration->id)->update([
            'form_data' => json_encode([
                'first_name' => 'Jean',
                'avs' => $oldCipher->encrypt('756.1234.5678.90'),
            ]),
        ]);

        $this->artisan('members:reencrypt-avs', [
            '--old-key' => $this->oldKey,
            '--new-key' => $this->newKey,
        ])->assertSuccessful();

        $memberRaw = DB::table('members')->where('id', $member->id)->value('avs');
        $this->assertSame('756.1234.5678.90', $newCipher->decrypt($memberRaw));
        $this->assertSame($newCipher->hash('7561234567890'), DB::table('members')->where('id', $member->id)->value('avs_hash'));

        $formData = json_decode((string) DB::table('member_registrations')->where('id', $registration->id)->value('form_data'), true);
        $this->assertSame('756.1234.5678.90', $newCipher->decrypt($formData['avs']));
        $this->assertSame('Jean', $formData['first_name']);
    }

    public function test_is_idempotent(): void
    {
        $newCipher = new MemberDataCipher($this->newKey);

        $member = Member::factory()->create();
        DB::table('members')->where('id', $member->id)->update([
            'avs' => $newCipher->encrypt('756.1234.5678.90'),
            'avs_hash' => $newCipher->hash('7561234567890'),
        ]);

        $this->artisan('members:reencrypt-avs', [
            '--old-key' => $this->newKey,
            '--new-key' => $this->newKey,
        ])->expectsOutputToContain('Skipped 1 value(s) already using the new key.')
            ->assertSuccessful();
    }

    public function test_fails_when_old_key_cannot_decrypt(): void
    {
        $oldCipher = new MemberDataCipher($this->oldKey);

        $member = Member::factory()->create();
        DB::table('members')->where('id', $member->id)->update([
            'avs' => $oldCipher->encrypt('756.1234.5678.90'),
            'avs_hash' => null,
        ]);

        $this->artisan('members:reencrypt-avs', [
            '--old-key' => 'base64:'.base64_encode(str_repeat('c', 32)),
            '--new-key' => $this->newKey,
        ])->assertFailed();
    }

    public function test_requires_both_keys(): void
    {
        config(['members.encryption_key' => null, 'members.previous_encryption_key' => null]);

        $this->artisan('members:reencrypt-avs')->assertFailed();
    }
}
