<?php

namespace App\Providers;

use App\Domains\Invoicing\Listeners\SyncBillingCustomerFromMember;
use App\Domains\Invoicing\Services\FinancialDocumentGenerator;
use App\Domains\Invoicing\Services\PdfFinancialDocumentGenerator;
use App\Events\MemberProfileUpdated;
use App\Services\DavServiceInterface;
use App\Services\InfomaniakDavService;
use App\Services\StubDavService;
use App\TokenAbility;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use RuntimeException;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->bind(FinancialDocumentGenerator::class, PdfFinancialDocumentGenerator::class);

        $this->app->singleton(DavServiceInterface::class, function () {
            if (config('dav.username') && config('dav.password')) {
                return new InfomaniakDavService;
            }

            if (app()->environment(['local', 'testing', 'staging'])) {
                return new StubDavService;
            }

            throw new RuntimeException('DAV credentials are not configured.');
        });
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Event::listen(MemberProfileUpdated::class, SyncBillingCustomerFromMember::class);

        $this->overrideSanctumConfigurationToSupportRefreshToken();
        $this->forceCanonicalUrls();
        $this->defineRateLimiters();
    }

    private function forceCanonicalUrls(): void
    {
        $url = (string) config('app.url');

        URL::useOrigin($url);
        URL::forceScheme(parse_url($url, PHP_URL_SCHEME) ?: 'https');
    }

    private function defineRateLimiters(): void
    {
        RateLimiter::for('login', fn (Request $request) => [
            Limit::perMinute(10)->by($request->ip()),
        ]);

        RateLimiter::for('api-login', fn (Request $request) => [
            Limit::perMinute(5)->by(Str::transliterate(
                Str::lower($request->string('email')).'|'.$request->ip()
            )),
            Limit::perMinute(10)->by($request->ip()),
        ]);

        RateLimiter::for('forgot-password', fn (Request $request) => [
            Limit::perMinutes(10, 3)->by($request->ip()),
        ]);

        RateLimiter::for('reset-password', fn (Request $request) => [
            Limit::perMinutes(10, 5)->by($request->ip()),
        ]);

        RateLimiter::for('register', fn (Request $request) => [
            Limit::perHour(3)->by($request->ip()),
        ]);

        RateLimiter::for('confirm-password', fn (Request $request) => [
            Limit::perMinute(6)->by($request->user()?->getAuthIdentifier() ?? $request->ip()),
        ]);

        RateLimiter::for('member-registration-verify-email', fn (Request $request) => [
            Limit::perMinute(10)
                ->by('verify:'.$request->string('form_uuid').'|'.$request->ip())
                ->response(fn () => back()->withErrors([
                    'code' => 'Trop de tentatives. Réessayez dans une minute.',
                ])),
        ]);

        RateLimiter::for('member-registration-resend-verification', fn (Request $request) => [
            Limit::perMinute(1)
                ->by('resend-minute:'.$request->string('form_uuid').'|'.$request->ip())
                ->response(fn () => back()->withErrors([
                    'code' => 'Veuillez patienter avant de demander un nouveau code.',
                ])),
            Limit::perHour(5)
                ->by('resend-hour-registration:'.$request->string('form_uuid'))
                ->response(fn () => back()->withErrors([
                    'code' => 'Trop de codes demandés pour cette inscription. Réessayez plus tard.',
                ])),
            Limit::perHour(5)
                ->by('resend-hour-ip:'.$request->ip())
                ->response(fn () => back()->withErrors([
                    'code' => 'Trop de codes demandés. Réessayez plus tard.',
                ])),
        ]);
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
