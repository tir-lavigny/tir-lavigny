<?php

use App\Http\Controllers\HomeController;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::get('/', [HomeController::class, 'index'])->name('home');

Route::get('/events', function () {
    return Inertia::render('Events');
})->name('events');

Route::get('/events/{id}', function () {
    return Inertia::render('Events');
})->name('events.show');

Route::get('/shootings', function () {
    return Inertia::render('Shootings');
})->name('shootings');


Route::get('dashboard', function () {
    return Inertia::render('Dashboard');
})->middleware(['auth', 'verified'])->name('dashboard');

require __DIR__ . '/settings.php';
require __DIR__ . '/auth.php';
