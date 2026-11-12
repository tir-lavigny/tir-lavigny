<?php

namespace App\Providers;

use App\Services\DavServiceInterface;
use App\Services\InfomaniakDavService;
use App\Services\StubDavService;
use App\TokenAbility;
use Illuminate\Database\Events\ConnectionEstablished;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use PDO;

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
        $this->registerSqliteUnaccentFunction();
    }

    private function registerSqliteUnaccentFunction(): void
    {
        Event::listen(ConnectionEstablished::class, function (ConnectionEstablished $event): void {
            if ($event->connection->getDriverName() !== 'sqlite') {
                return;
            }

            $pdo = $event->connection->getPdo();

            if (! $pdo instanceof PDO) {
                return;
            }

            $pdo->sqliteCreateFunction(
                'unaccent',
                fn (?string $value): ?string => $value === null ? null : Str::lower(Str::ascii($value)),
                1,
            );
        });
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
