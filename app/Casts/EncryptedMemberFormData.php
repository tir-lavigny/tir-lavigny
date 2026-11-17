<?php

namespace App\Casts;

use App\Support\Avs;
use App\Support\MemberDataCipher;
use Illuminate\Contracts\Database\Eloquent\CastsAttributes;
use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Log;

/**
 * @implements CastsAttributes<array<string, mixed>|null, mixed>
 */
class EncryptedMemberFormData implements CastsAttributes
{
    public function get(Model $model, string $key, mixed $value, array $attributes): ?array
    {
        if ($value === null || $value === '') {
            return null;
        }

        $data = json_decode((string) $value, true);

        if (! is_array($data)) {
            return null;
        }

        if (! isset($data['avs']) || $data['avs'] === '') {
            return $data;
        }

        try {
            $data['avs'] = app(MemberDataCipher::class)->decrypt((string) $data['avs']);
        } catch (DecryptException) {
            if (! Avs::looksLike($data['avs'])) {
                Log::warning('Unable to decrypt member registration AVS.', [
                    'model' => $model::class,
                    'id' => $model->getKey(),
                ]);

                $data['avs'] = null;
            }
        }

        return $data;
    }

    public function set(Model $model, string $key, mixed $value, array $attributes): ?string
    {
        if ($value === null) {
            return null;
        }

        if (! is_array($value)) {
            return null;
        }

        if (isset($value['avs']) && $value['avs'] !== '') {
            $value['avs'] = app(MemberDataCipher::class)->encrypt((string) $value['avs']);
        }

        return json_encode($value, JSON_THROW_ON_ERROR);
    }
}
