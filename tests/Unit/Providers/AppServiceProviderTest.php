<?php

namespace Tests\Unit\Providers;

use App\Services\DavServiceInterface;
use App\Services\InfomaniakDavService;
use App\Services\StubDavService;
use RuntimeException;
use Tests\TestCase;

class AppServiceProviderTest extends TestCase
{
    public function test_throws_when_dav_credentials_are_missing_outside_local(): void
    {
        config(['dav.username' => '', 'dav.password' => '']);
        $this->app['env'] = 'production';

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('DAV credentials are not configured.');

        app(DavServiceInterface::class);
    }

    public function test_uses_stub_when_credentials_are_missing_in_local(): void
    {
        config(['dav.username' => '', 'dav.password' => '']);
        $this->app['env'] = 'local';
        $this->app->forgetInstance(DavServiceInterface::class);

        $this->assertInstanceOf(StubDavService::class, app(DavServiceInterface::class));
    }

    public function test_uses_infomaniak_when_credentials_are_present(): void
    {
        config(['dav.username' => 'user', 'dav.password' => 'secret']);
        $this->app->forgetInstance(DavServiceInterface::class);

        $this->assertInstanceOf(InfomaniakDavService::class, app(DavServiceInterface::class));
    }
}
