<?php

namespace App\Http\Middleware;

use App\Support\Seo;
use Illuminate\Foundation\Inspiring;
use Illuminate\Http\Request;
use Illuminate\Routing\Route;
use Illuminate\Support\Str;
use Inertia\Middleware;
use Tighten\Ziggy\Ziggy;

class HandleInertiaRequests extends Middleware
{
    /**
     * The root template that's loaded on the first page visit.
     *
     * @see https://inertiajs.com/server-side-setup#root-template
     *
     * @var string
     */
    protected $rootView = 'app';

    /**
     * Determines the current asset version.
     *
     * @see https://inertiajs.com/asset-versioning
     */
    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    /**
     * Define the props that are shared by default.
     *
     * @see https://inertiajs.com/shared-data
     *
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        [$message, $author] = str(Inspiring::quotes()->random())->explode('-');

        return [
            ...parent::share($request),
            'name' => config('app.name'),
            'env' => config('app.env'),
            'quote' => ['message' => trim($message), 'author' => trim($author)],
            'auth' => [
                'user' => $request->user()?->load('roles', 'roles.permissions'),
            ],
            'ziggy' => [
                ...(new Ziggy)->filter($this->ziggyRouteNames($request))->toArray(),
                'location' => $request->url(),
            ],
            'sidebarOpen' => ! $request->hasCookie('sidebar_state') || $request->cookie('sidebar_state') === 'true',
            'head' => Seo::defaultHead(),
        ];
    }

    /**
     * Named routes that are safe to expose to the Inertia client.
     *
     * Guests only receive routes they can reach without signing in, so the
     * back-office, API and tooling surface is not disclosed anonymously.
     *
     * @return array<int, string>
     */
    private function ziggyRouteNames(Request $request): array
    {
        $isAuthenticated = $request->user() !== null;

        return collect(app('router')->getRoutes()->getRoutesByName())
            ->reject(fn (Route $route, string $name): bool => str_starts_with($name, 'generated::')
                || Str::startsWith($name, ['telescope', 'scramble.', 'boost.', 'sanctum.', 'storage.']))
            ->filter(fn (Route $route, string $name): bool => $isAuthenticated
                || $name === 'dashboard'
                || $this->isPublicRoute($route))
            ->keys()
            ->values()
            ->all();
    }

    /**
     * Determine whether a route is reachable without an authenticated user.
     */
    private function isPublicRoute(Route $route): bool
    {
        foreach ($route->gatherMiddleware() as $middleware) {
            if (! is_string($middleware)) {
                continue;
            }

            if (in_array($middleware, ['auth', 'verified'], true)
                || Str::startsWith($middleware, ['auth:', 'permission:', 'role:', 'can:', 'role_or_permission:'])) {
                return false;
            }
        }

        return true;
    }
}
