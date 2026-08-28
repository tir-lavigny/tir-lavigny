<?php

namespace App\DTOs;

use App\Models\User;

class TokenData
{
    public function __construct(
        public readonly string $accessToken,
        public readonly ?User $user = null,
    ) {}
}
