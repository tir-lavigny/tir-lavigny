<?php

namespace Tests\Feature\Console;

use App\Support\MemberDataCipher;
use Tests\TestCase;

class MembersGenerateKeyTest extends TestCase
{
    public function test_generates_key_in_existing_environment_file(): void
    {
        $path = $this->temporaryEnvFile("APP_NAME=test\nMEMBER_DATA_KEY=\n");

        $this->artisan('members:key', ['--path' => $path])
            ->assertSuccessful();

        $contents = (string) file_get_contents($path);
        preg_match('/^MEMBER_DATA_KEY=(.*)$/m', $contents, $matches);

        $key = $matches[1] ?? '';

        $this->assertNotSame('', $key);

        $cipher = new MemberDataCipher($key);
        $this->assertSame('756.1234.5678.90', $cipher->decrypt($cipher->encrypt('756.1234.5678.90')));

        unlink($path);
    }

    public function test_appends_key_when_missing(): void
    {
        $path = $this->temporaryEnvFile("APP_NAME=test\n");

        $this->artisan('members:key', ['--path' => $path])
            ->assertSuccessful();

        $contents = (string) file_get_contents($path);

        $this->assertStringContainsString('MEMBER_DATA_KEY=', $contents);

        unlink($path);
    }

    public function test_fails_when_environment_file_is_missing(): void
    {
        $this->artisan('members:key', ['--path' => '/tmp/opencode/does-not-exist.env'])
            ->assertFailed();
    }

    private function temporaryEnvFile(string $contents): string
    {
        $path = tempnam('/tmp/opencode', 'member-key-test-');

        file_put_contents($path, $contents);

        return $path;
    }
}
