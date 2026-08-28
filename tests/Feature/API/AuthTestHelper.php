<?php

namespace Tests\Feature\API;

use App\Models\User;
use App\TokenAbility;
use Laravel\Sanctum\PersonalAccessToken;

class AuthTestHelper
{
    public static array $loginSuccessBody = [
        'accessToken',
        'user' => [
            'id',
            'email',
        ],
    ];

    public static array $refreshSuccessBody = [
        'accessToken',
    ];

    public static function mockUser(): User
    {
        return User::factory()->create();
    }

    public static function clearUser(User $userModel): void
    {
        $userModel->tokens()->delete();
        $userModel->delete($userModel->id);
    }

    /**
     * @return array{
     *     accessToken: string,
     *     refreshToken: string,
     * }
     */
    public static function generateTokens(User $user): array
    {
        $accessToken = $user->createToken(
            'access_token',
            [TokenAbility::ACCESS_API],
            now()->addMinutes(config('sanctum.expiration')),
        );
        $refreshToken = $user->createToken(
            'refresh_token',
            [TokenAbility::ISSUE_ACCESS_TOKEN],
            now()->addMinutes(config('sanctum.rt_expiration')),
        );

        return [
            'accessToken' => $accessToken->plainTextToken,
            'refreshToken' => $refreshToken->plainTextToken,
        ];
    }

    public static function verifyAccessToken(string $accessToken): bool
    {
        $tokenInDb = PersonalAccessToken::findToken($accessToken);

        return $tokenInDb && $tokenInDb->expires_at->isFuture() && $tokenInDb->can(TokenAbility::ACCESS_API->value);
    }
}
