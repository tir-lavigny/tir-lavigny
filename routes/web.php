<?php

use App\Http\Controllers\BackOffice\DashboardController;
use App\Http\Controllers\BackOffice\EventController as BackOfficeEventController;
use App\Http\Controllers\BackOffice\PostController as BackOfficePostController;
use App\Http\Controllers\ContentController;
use App\Http\Controllers\EventController;
use App\Http\Controllers\HomeController;
use App\Http\Controllers\LocationController;
use App\Http\Controllers\PostController;
use App\Http\Controllers\ShootingController;
use Illuminate\Support\Facades\Route;

Route::get('/', [HomeController::class, 'index'])->name('home');

Route::get('/events', [EventController::class, 'index'])->name('events');
Route::get('/events/{id}', [EventController::class, 'show'])->name('events.show');
Route::get('/events/calendar/feed.ics', [EventController::class, 'feed_calendar'])->name('events.feed_calendar');

Route::get('/posts/{public_id}', [PostController::class, 'show'])->name('posts.show');

Route::get('/shootings', [ShootingController::class, 'index'])->name('shootings');

Route::prefix('back-office')->middleware(['auth', 'verified'])->group(function () {
    Route::get('/', function () {
        return redirect()->route('dashboard');
    })->name('back-office');

    Route::get('dashboard', [DashboardController::class, 'index'])->name('dashboard');

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
    Route::resource('posts', BackOfficePostController::class)->names([
        'index' => 'bo.posts.index',
        'create' => 'bo.posts.create',
        'store' => 'bo.posts.store',
        'show' => 'bo.posts.show',
        'edit' => 'bo.posts.edit',
        'update' => 'bo.posts.update',
        'destroy' => 'bo.posts.destroy',
    ]);

    require __DIR__.'/settings.php';
});

require __DIR__.'/auth.php';
