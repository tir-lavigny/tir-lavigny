<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @property-read int $id
 * @property-read string $name
 * @property-read string $email
 */
class UserResource extends JsonResource
{
    /**
     * @return array{id: int, name: string, email: string, roles: array<int, array{name: string, permissions: string[]}>}
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'email' => $this->email,
            'roles' => $this->roles->map(fn ($role) => [
                'name' => $role->name,
                'permissions' => $role->permissions->pluck('name')->unique()->values()->toArray(),
            ]),
        ];
    }
}
