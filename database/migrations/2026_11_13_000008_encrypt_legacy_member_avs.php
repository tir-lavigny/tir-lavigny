<?php

use App\Support\Avs;
use App\Support\MemberDataCipher;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

return new class extends Migration
{
    public function up(): void
    {
        $cipher = app(MemberDataCipher::class);

        DB::table('members')
            ->whereNotNull('avs')
            ->where('avs', '!=', '')
            ->orderBy('id')
            ->chunkById(100, function ($members) use ($cipher): void {
                foreach ($members as $member) {
                    $plaintext = (string) $member->avs;

                    try {
                        $decrypted = $cipher->decrypt($plaintext);

                        if (Avs::looksLike($decrypted)) {
                            DB::table('members')->where('id', $member->id)->update([
                                'avs_hash' => $cipher->hash(preg_replace('/\D/', '', $decrypted) ?? ''),
                            ]);
                        }

                        continue;
                    } catch (Throwable) {
                        // Legacy plaintext value, encrypt it below.
                    }

                    if (! Avs::looksLike($plaintext)) {
                        Log::warning('Skipped member AVS encryption: value is not a legacy AVS.', [
                            'member_id' => $member->id,
                        ]);

                        continue;
                    }

                    DB::table('members')->where('id', $member->id)->update([
                        'avs' => $cipher->encrypt($plaintext),
                        'avs_hash' => $cipher->hash(preg_replace('/\D/', '', $plaintext) ?? ''),
                    ]);
                }
            });
    }

    public function down(): void
    {
        // Encrypted values cannot be reverted to plaintext.
    }
};
