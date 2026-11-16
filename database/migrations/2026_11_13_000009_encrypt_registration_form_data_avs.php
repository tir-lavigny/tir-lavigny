<?php

use App\Support\Avs;
use App\Support\MemberDataCipher;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $cipher = app(MemberDataCipher::class);

        DB::table('member_registrations')
            ->whereNotNull('form_data')
            ->orderBy('id')
            ->chunkById(100, function ($registrations) use ($cipher): void {
                foreach ($registrations as $registration) {
                    $data = json_decode((string) $registration->form_data, true);

                    if (! is_array($data) || ! isset($data['avs']) || $data['avs'] === null || $data['avs'] === '') {
                        continue;
                    }

                    $avs = (string) $data['avs'];

                    try {
                        $cipher->decrypt($avs);

                        continue;
                    } catch (Throwable) {
                        // Legacy plaintext value, encrypt it below.
                    }

                    if (! Avs::looksLike($avs)) {
                        continue;
                    }

                    $data['avs'] = $cipher->encrypt($avs);

                    DB::table('member_registrations')
                        ->where('id', $registration->id)
                        ->update(['form_data' => json_encode($data)]);
                }
            });
    }

    public function down(): void
    {
        // Encrypted values cannot be reverted to plaintext.
    }
};
