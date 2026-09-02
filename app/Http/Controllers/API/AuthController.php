<?php

namespace App\Http\Controllers\API;

use App\DTOs\TokenData;
use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use App\Http\Resources\TokenResource;
use App\Http\Resources\UserResource;
use App\Http\Responses\ApiResponse;
use App\Models\User;
use App\TokenAbility;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Cookie;

class AuthController extends Controller
{
    use ApiResponse;

    public function login(LoginRequest $request): JsonResponse
    {
        $credentials = $request->validated();
        if (! Auth::attempt($credentials)) {
            return $this->error('Wrong credentials.', 401);
        }

        $user = Auth::user()?->load('roles', 'roles.permissions');
        $tokens = $this->generateTokens($user);

        return (new TokenResource(new TokenData($tokens['accessToken'], $user)))
            ->response()
            ->withCookie($this->refreshCookie($tokens['refreshToken']));
    }

    public function refresh(Request $request): JsonResponse
    {
        $request->user()->tokens()->delete();
        $tokens = $this->generateTokens($request->user());

        return (new TokenResource(new TokenData($tokens['accessToken'])))
            ->response()
            ->withCookie($this->refreshCookie($tokens['refreshToken']));
    }

    public function me(Request $request): JsonResponse
    {
        return (new UserResource($request->user()))
            ->response();
    }

    public function logout(Request $request): Response
    {
        $request->user()->tokens()->delete();

        return $this->noContent()->withCookie(
            cookie()->forget('refreshToken'),
        );
    }

    private function refreshCookie(string $refreshToken): Cookie
    {
        return cookie(
            'refreshToken',
            $refreshToken,
            config('sanctum.rt_expiration'),
            path: '/',
            secure: true,
            httpOnly: true,
            sameSite: 'lax',
        );
    }

    /**
     * @return array{
     *     accessToken: string,
     *     refreshToken: string,
     * }
     */
    private function generateTokens(User $user): array
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
}
