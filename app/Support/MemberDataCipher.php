<?php

namespace App\Support;

use Illuminate\Encryption\Encrypter;
use Illuminate\Support\Str;
use RuntimeException;

class MemberDataCipher
{
    private Encrypter $encrypter;

    public function __construct()
    {
        $key = config('members.encryption_key');

        if ((! is_string($key) || $key === '') && app()->environment('production')) {
            throw new RuntimeException('MEMBER_DATA_KEY is not configured.');
        }

        $key = $key ?: config('app.key');

        if (! is_string($key) || $key === '') {
            throw new RuntimeException('MEMBER_DATA_KEY is not configured.');
        }

        if (Str::startsWith($key, 'base64:')) {
            $key = base64_decode(Str::after($key, 'base64:'), true);

            if ($key === false) {
                throw new RuntimeException('MEMBER_DATA_KEY is not valid base64.');
            }
        }

        $this->encrypter = new Encrypter($key, config('app.cipher', 'aes-256-cbc'));
    }

    public function encrypt(string $value): string
    {
        return $this->encrypter->encryptString($value);
    }

    public function decrypt(string $value): string
    {
        return $this->encrypter->decryptString($value);
    }

    public function hash(string $value): string
    {
        return hash_hmac('sha256', $value, $this->key());
    }

    private function key(): string
    {
        return $this->encrypter->getKey();
    }
}
