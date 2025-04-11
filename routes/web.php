<?php

use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::get('/', function () {
    return Inertia::render('Home');
})->name('home');

Route::get('/stand', function () {
    return Inertia::render('Stand');
})->name('stand');

Route::get('/events', function () {
    return Inertia::render('Events');
})->name('events');

Route::get('/shootings', function () {
    return Inertia::render('Shootings');
})->name('shootings');


Route::get('dashboard', function () {
    return Inertia::render('Dashboard');
})->middleware(['auth', 'verified'])->name('dashboard');

require __DIR__ . '/settings.php';
require __DIR__ . '/auth.php';
