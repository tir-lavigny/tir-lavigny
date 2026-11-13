<?php

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

                    if (! $this->looksLikeAvs($avs)) {
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

    private function looksLikeAvs(string $value): bool
    {
        if (preg_match('/^756\.\d{4}\.\d{4}\.\d{2}$/', $value) === 1) {
            return true;
        }

        $digits = preg_replace('/\D/', '', $value) ?? '';

        return strlen($digits) === 13;
    }
};
