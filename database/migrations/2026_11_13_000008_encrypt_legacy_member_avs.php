<?php

use App\Support\MemberDataCipher;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

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
                        $cipher->decrypt($plaintext);

                        continue;
                    } catch (Throwable) {
                        // Legacy plaintext value, encrypt it below.
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
