<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::command('telescope:prune')->weekly();
Schedule::command('idempotency:prune')->daily();
Schedule::command('activitylogs:prune')->daily();

Schedule::command('queue:work', [
    '--stop-when-empty',
    '--max-time=50',
    '--tries=3',
    '--backoff=60',
])
    ->everyMinute()
    ->withoutOverlapping(2);
