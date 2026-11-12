<?php

namespace App\Providers;

use App\Services\DavServiceInterface;
use App\Services\InfomaniakDavService;
use App\Services\StubDavService;
use App\TokenAbility;
use Illuminate\Support\ServiceProvider;
use Laravel\Sanctum\Sanctum;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->singleton(DavServiceInterface::class, function () {
            if (config('dav.username') && config('dav.password')) {
                return new InfomaniakDavService;
            }

            return new StubDavService;
        });
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->overrideSanctumConfigurationToSupportRefreshToken();
    }

    private function overrideSanctumConfigurationToSupportRefreshToken(): void
    {
        Sanctum::$accessTokenAuthenticationCallback = function ($accessToken, $isValid) {
            if ($accessToken->can(TokenAbility::ISSUE_ACCESS_TOKEN->value)) {
                return $accessToken->expires_at && $accessToken->expires_at->isFuture();
            }

            return $isValid;
        };

        Sanctum::$accessTokenRetrievalCallback = function ($request) {
            if (! $request->routeIs('api.refresh')) {
                return str_replace('Bearer ', '', $request->headers->get('Authorization'));
            }

            return $request->cookie('refreshToken') ?? '';
        };
    }
}
