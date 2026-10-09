<?php

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;

Route::get('/up', function () {
    return response()->noContent();
})->name('health.up');

$healthCheck = function () {
    $failures = [];

    try {
        DB::select('select 1');
    } catch (Throwable $e) {
        $failures['database'] = $e->getMessage();
    }

    if (! is_writable(storage_path('app'))) {
        $failures['storage'] = storage_path('app').' is not writable';
    }

    if ($failures !== []) {
        logger()->error('Health check failed', $failures);

        return response()->json(['status' => 'degraded'], 503);
    }

    return response()->json(['status' => 'ok']);
};

Route::get('/health', $healthCheck)->name('health.check');

// The register PWA's offline detection pings /api/health (HEAD).
Route::match(['get', 'head'], '/api/health', $healthCheck)->name('health.check.api');
