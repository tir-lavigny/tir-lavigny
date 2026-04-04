<?php

use App\Providers\AppServiceProvider;
use App\Providers\LaravelPulse4xxServiceProvider;
use App\Providers\PulseRequestsServiceProvider;
use App\Providers\TelescopeServiceProvider;

return [
    AppServiceProvider::class,
    LaravelPulse4xxServiceProvider::class,
    PulseRequestsServiceProvider::class,
    TelescopeServiceProvider::class,
];
