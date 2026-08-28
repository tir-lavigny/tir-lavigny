<?php

namespace App\Http\Resources;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class TokenResource extends JsonResource
{
    public static $wrap = null;

    public function __construct(
        string $accessToken,
        ?User $user = null,
    ) {
        parent::__construct([
            'accessToken' => $accessToken,
            'user' => $user ? UserResource::make($user) : null,
        ]);
    }

    public function toArray(Request $request): array
    {
        return array_filter([
            'accessToken' => $this->resource['accessToken'],
            'user' => $this->resource['user'],
        ], fn ($v) => $v !== null);
    }
}
