<?php

namespace App\Casts;

use App\Support\MemberDataCipher;
use Illuminate\Contracts\Database\Eloquent\CastsAttributes;
use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Log;

/**
 * @implements CastsAttributes<string|null, string|null>
 */
class EncryptedMemberData implements CastsAttributes
{
    public function get(Model $model, string $key, mixed $value, array $attributes): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        try {
            return app(MemberDataCipher::class)->decrypt((string) $value);
        } catch (DecryptException $exception) {
            Log::warning('Unable to decrypt member data.', [
                'model' => $model::class,
                'id' => $model->getKey(),
                'attribute' => $key,
            ]);

            return null;
        }
    }

    public function set(Model $model, string $key, mixed $value, array $attributes): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        return app(MemberDataCipher::class)->encrypt((string) $value);
    }
}
