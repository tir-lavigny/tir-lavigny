<?php

use App\Http\Controllers\API\AuthController;
use Illuminate\Support\Facades\Route;

Route::get('/health', function () {
    return response()->json(['status' => 'ok']);
});
Route::middleware('auth:sanctum')->get('test', fn () => response()->json(['ok' => true]))->name('test');

Route::prefix('auth')->group(function () {
    Route::post('login', [AuthController::class, 'login'])
        ->name('api.login');

    Route::middleware('auth:sanctum')->group(function () {
        Route::post('refresh-token', [AuthController::class, 'refresh'])
            ->name('api.refresh');
        Route::post('logout', [AuthController::class, 'logout'])
            ->name('api.logout');
    });
});
