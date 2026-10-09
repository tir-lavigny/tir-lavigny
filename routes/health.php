<?php

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;

Route::get('/up', function () {
    return response()->noContent();
})->name('health.up');

$healthCheck = function () {
    try {
        DB::select('select 1');
        $db = 'ok';
    } catch (Throwable) {
        $db = 'failed';
    }

    $storage = is_writable(storage_path('app')) ? 'ok' : 'failed';

    $status = ($db === 'ok' && $storage === 'ok') ? 200 : 503;

    return response()->json([
        'status' => $status === 200 ? 'ok' : 'degraded',
        'checks' => [
            'database' => $db,
            'storage' => $storage,
        ],
    ], $status);
};

Route::get('/health', $healthCheck)->name('health.check');

// The register PWA's offline detection pings /api/health (HEAD).
Route::match(['get', 'head'], '/api/health', $healthCheck)->name('health.check.api');
