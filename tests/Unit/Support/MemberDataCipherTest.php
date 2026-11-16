<?php

namespace Tests\Unit\Support;

use App\Support\MemberDataCipher;
use Illuminate\Contracts\Encryption\DecryptException;
use RuntimeException;
use Tests\TestCase;

class MemberDataCipherTest extends TestCase
{
    private function key(string $char): string
    {
        return 'base64:'.base64_encode(str_repeat($char, 32));
    }

    public function test_throws_when_key_config_missing(): void
    {
        config(['members.encryption_key' => null]);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('MEMBER_DATA_KEY is not configured.');

        new MemberDataCipher;
    }

    public function test_throws_when_key_config_empty(): void
    {
        config(['members.encryption_key' => '']);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('MEMBER_DATA_KEY is not configured.');

        new MemberDataCipher;
    }

    public function test_uses_configured_key(): void
    {
        config(['members.encryption_key' => $this->key('c')]);

        $cipher = new MemberDataCipher;
        $encrypted = $cipher->encrypt('756.1234.5678.90');

        $this->assertSame('756.1234.5678.90', $cipher->decrypt($encrypted));
    }

    public function test_encrypts_and_decrypts_with_explicit_key(): void
    {
        $cipher = new MemberDataCipher($this->key('a'));

        $encrypted = $cipher->encrypt('756.1234.5678.90');

        $this->assertNotSame('756.1234.5678.90', $encrypted);
        $this->assertSame('756.1234.5678.90', $cipher->decrypt($encrypted));
    }

    public function test_different_keys_do_not_decrypt_each_other(): void
    {
        $oldCipher = new MemberDataCipher($this->key('a'));
        $newCipher = new MemberDataCipher($this->key('b'));

        $encrypted = $oldCipher->encrypt('756.1234.5678.90');

        $this->expectException(DecryptException::class);
        $newCipher->decrypt($encrypted);
    }

    public function test_hash_is_deterministic_per_key(): void
    {
        $first = new MemberDataCipher($this->key('a'));
        $second = new MemberDataCipher($this->key('a'));
        $other = new MemberDataCipher($this->key('b'));

        $this->assertSame($first->hash('7561234567890'), $second->hash('7561234567890'));
        $this->assertNotSame($first->hash('7561234567890'), $other->hash('7561234567890'));
    }
}
