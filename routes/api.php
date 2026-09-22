<?php

use App\Http\Controllers\API\AuthController;
use App\Http\Controllers\API\Register;
use App\Http\Controllers\API\Stock\StockLocationController;
use App\Http\Controllers\API\Stock\StockLossController;
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
        Route::get('me', [AuthController::class, 'me'])
            ->name('api.me');
        Route::post('logout', [AuthController::class, 'logout'])
            ->name('api.logout');
    });
});

Route::middleware(['auth:sanctum', 'role:cashier|admin'])->prefix('register')->group(function () {
    Route::get('/seasons/current', [Register\SeasonController::class, 'getCurrentSeason'])
        ->name('api.register.seasons.current');

    Route::post('/days', [Register\OperatingDayController::class, 'store'])
        ->name('api.register.operating-days.store');
    Route::patch('/days/{operatingDay}', [Register\OperatingDayController::class, 'update'])
        ->name('api.register.operating-days.update');
    Route::get('/days/current', [Register\OperatingDayController::class, 'current'])
        ->name('api.register.operating-days.current');
    Route::get('/days/previous-unclosed', [Register\OperatingDayController::class, 'previousUnclosed'])
        ->name('api.register.operating-days.previous-unclosed');
    Route::get('/days/{operatingDay}', [Register\OperatingDayController::class, 'show'])
        ->name('api.register.operating-days.show');

    Route::post('/tickets', [Register\TicketController::class, 'store'])
        ->name('api.register.tickets.store');
    Route::post('/tickets/preview', [Register\TicketController::class, 'preview'])
        ->name('api.register.tickets.preview');
    Route::get('/tickets/{ticket}', [Register\TicketController::class, 'show'])
        ->name('api.register.tickets.show');
    Route::post('/tickets/{ticket}/refund', [Register\TicketController::class, 'refund'])
        ->name('api.register.tickets.refund');

    Route::get('/products', [Register\ProductController::class, 'index'])
        ->name('api.register.products.index');
    Route::get('/products/{product}', [Register\ProductController::class, 'show'])
        ->name('api.register.products.show');
    Route::get('/categories', [Register\CategoryController::class, 'index'])
        ->name('api.register.categories.index');

    Route::get('/members', [Register\MemberController::class, 'index'])
        ->name('api.register.members.index');
    Route::get('/members/{member}/vouchers', [Register\MemberController::class, 'vouchers'])
        ->name('api.register.members.vouchers');
});

Route::middleware(['auth:sanctum', 'role:cashier|admin'])->prefix('stock')->group(function () {
    Route::get('/locations', [StockLocationController::class, 'index'])
        ->name('api.stock.locations.index');

    Route::get('{stockLocation}/{operatingDay}/losses', [StockLossController::class, 'index'])
        ->name('api.stock.losses.index');
    Route::post('losses', [StockLossController::class, 'store'])
        ->name('api.stock.losses.store');
});
