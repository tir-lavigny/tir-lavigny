<?php

namespace App\Http\Resources;

use App\DTOs\TokenData;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @property-read TokenData $resource
 */
class TokenResource extends JsonResource
{
    public static $wrap = null;

    public function __construct(TokenData $data)
    {
        parent::__construct($data);
    }

    public function toArray(Request $request): array
    {
        return [
            'accessToken' => $this->resource->accessToken,
            'user' => $this->resource->user ? new UserResource($this->resource->user) : null,
        ];
    }
}
