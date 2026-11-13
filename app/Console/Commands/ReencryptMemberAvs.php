<?php

namespace App\Console\Commands;

use App\Support\MemberDataCipher;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Throwable;

class ReencryptMemberAvs extends Command
{
    protected $signature = 'members:reencrypt-avs
        {--old-key= : Previous MEMBER_DATA_KEY}
        {--new-key= : New MEMBER_DATA_KEY (defaults to config)}';

    protected $description = 'Re-encrypt member and registration AVS values when MEMBER_DATA_KEY rotates.';

    public function handle(): int
    {
        $oldKey = $this->option('old-key') ?: config('members.previous_encryption_key');
        $newKey = $this->option('new-key') ?: config('members.encryption_key');

        if (! is_string($oldKey) || $oldKey === '' || ! is_string($newKey) || $newKey === '') {
            $this->error('Both the old and new MEMBER_DATA_KEY values are required.');

            return self::FAILURE;
        }

        $oldCipher = new MemberDataCipher($oldKey);
        $newCipher = new MemberDataCipher($newKey);

        $members = 0;
        $registrations = 0;
        $skipped = 0;
        $failed = 0;

        DB::table('members')
            ->whereNotNull('avs')
            ->where('avs', '!=', '')
            ->orderBy('id')
            ->chunkById(100, function ($rows) use ($oldCipher, $newCipher, &$members, &$skipped, &$failed): void {
                foreach ($rows as $member) {
                    $plaintext = $this->resolvePlaintext((string) $member->avs, $oldCipher, $newCipher, $skipped, $failed, 'member', $member->id);

                    if ($plaintext === null) {
                        continue;
                    }

                    DB::table('members')->where('id', $member->id)->update([
                        'avs' => $newCipher->encrypt($plaintext),
                        'avs_hash' => $newCipher->hash(preg_replace('/\D/', '', $plaintext) ?? ''),
                    ]);

                    $members++;
                }
            });

        DB::table('member_registrations')
            ->whereNotNull('form_data')
            ->orderBy('id')
            ->chunkById(100, function ($rows) use ($oldCipher, $newCipher, &$registrations, &$skipped, &$failed): void {
                foreach ($rows as $registration) {
                    $data = json_decode((string) $registration->form_data, true);

                    if (! is_array($data) || ! isset($data['avs']) || $data['avs'] === null || $data['avs'] === '') {
                        continue;
                    }

                    $plaintext = $this->resolvePlaintext((string) $data['avs'], $oldCipher, $newCipher, $skipped, $failed, 'registration', $registration->id);

                    if ($plaintext === null) {
                        continue;
                    }

                    $data['avs'] = $newCipher->encrypt($plaintext);

                    DB::table('member_registrations')
                        ->where('id', $registration->id)
                        ->update(['form_data' => json_encode($data)]);

                    $registrations++;
                }
            });

        $this->info("Re-encrypted {$members} member(s) and {$registrations} registration(s).");
        $this->line("Skipped {$skipped} value(s) already using the new key.");

        if ($failed > 0) {
            $this->error("Unable to re-encrypt {$failed} value(s). Check the keys and logs.");

            return self::FAILURE;
        }

        return self::SUCCESS;
    }

    private function resolvePlaintext(
        string $value,
        MemberDataCipher $oldCipher,
        MemberDataCipher $newCipher,
        int &$skipped,
        int &$failed,
        string $type,
        int $id,
    ): ?string {
        try {
            $newCipher->decrypt($value);
            $skipped++;

            return null;
        } catch (Throwable) {
            // Value still uses the old key or is legacy plaintext.
        }

        try {
            return $oldCipher->decrypt($value);
        } catch (Throwable) {
            if ($this->looksLikeAvs($value)) {
                return $value;
            }

            $failed++;
            $this->warn("Unable to decrypt {$type} #{$id}.");

            return null;
        }
    }

    private function looksLikeAvs(string $value): bool
    {
        if (preg_match('/^756\.\d{4}\.\d{4}\.\d{2}$/', $value) === 1) {
            return true;
        }

        $digits = preg_replace('/\D/', '', $value) ?? '';

        return strlen($digits) === 13;
    }
}
