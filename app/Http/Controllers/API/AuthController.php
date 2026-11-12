<?php

namespace App\Http\Controllers\API;

use App\DTOs\TokenData;
use App\Enums\AppAccess;
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
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Cookie;

class AuthController extends Controller
{
    use ApiResponse;

    public function login(LoginRequest $request): JsonResponse
    {
        $credentials = $request->validated();
        if (! Auth::attempt($credentials)) {
            Log::warning('API login failed', [
                'email_hash' => hash('sha256', strtolower((string) $credentials['email'])),
                'ip' => $request->ip(),
            ]);

            return $this->error('Wrong credentials.', 401);
        }

        $user = Auth::user();

        if (! $user instanceof User || ! $user->canAccessApp(AppAccess::Register)) {
            Auth::guard('web')->logout();

            Log::warning('API login denied: no register access', [
                'email_hash' => hash('sha256', strtolower((string) $credentials['email'])),
                'ip' => $request->ip(),
            ]);

            return $this->error('This account cannot access the register.', 403);
        }

        $user->load('roles', 'roles.permissions');
        $tokens = $this->generateTokens($user);

        return (new TokenResource(new TokenData($tokens['accessToken'], $user)))
            ->response()
            ->withCookie($this->refreshCookie($tokens['refreshToken']));
    }

    public function refresh(Request $request): JsonResponse
    {
        $user = $request->user();

        if (! $user instanceof User || ! $user->canAccessApp(AppAccess::Register)) {
            $user?->tokens()->delete();

            return $this->error('This account cannot access the register.', 403);
        }

        $user->tokens()->delete();
        $tokens = $this->generateTokens($user);

        return (new TokenResource(new TokenData($tokens['accessToken'])))
            ->response()
            ->withCookie($this->refreshCookie($tokens['refreshToken']));
    }

    public function me(Request $request): JsonResponse
    {
        $user = $request->user()->load('roles.permissions');

        return (new UserResource($user))
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
        $parsedHost = parse_url((string) config('app.url'), PHP_URL_HOST);
        $host = is_string($parsedHost) ? $parsedHost : '';
        $parts = explode('.', $host);
        $rootDomain = implode('.', array_slice($parts, -2));

        return cookie(
            'refreshToken',
            $refreshToken,
            config('sanctum.rt_expiration'),
            domain: '.'.$rootDomain,
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
