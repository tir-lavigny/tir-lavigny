<?php

use App\Http\Controllers\BackOffice\EventController as BackOfficeEventController;
use App\Http\Controllers\ContentController;
use App\Http\Controllers\EventController;
use App\Http\Controllers\HomeController;
use App\Http\Controllers\LocationController;
use App\Http\Controllers\ShootingController;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::get('/', [HomeController::class, 'index'])->name('home');

Route::get('/events', [EventController::class, 'index'])->name('events');
Route::get('/events/{id}', [EventController::class, 'show'])->name('events.show');

Route::get('/shootings', [ShootingController::class, 'index'])->name('shootings');

Route::prefix('back-office')->middleware(['auth', 'verified'])->group(function () {
    Route::get('/', function () {
        return redirect()->route('dashboard');
    })->name('back-office');

    Route::get('dashboard', function () {
        return Inertia::render('backOffice/Dashboard');
    })->name('dashboard');

    Route::resource('locations', LocationController::class)->except(['create', 'show', 'edit']);
    Route::resource('contents', ContentController::class)->except(['create', 'show', 'edit']);
    Route::resource('events', BackOfficeEventController::class)->names([
        'index' => 'bo.events.index',
        'create' => 'bo.events.create',
        'store' => 'bo.events.store',
        'show' => 'bo.events.show',
        'edit' => 'bo.events.edit',
        'update' => 'bo.events.update',
        'destroy' => 'bo.events.destroy',
    ]);

    require __DIR__.'/settings.php';
});

require __DIR__.'/auth.php';
