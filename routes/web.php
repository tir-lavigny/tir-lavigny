<?php

use App\Http\Controllers\BackOffice\DashboardController;
use App\Http\Controllers\BackOffice\DavController;
use App\Http\Controllers\BackOffice\EventChainController as BackOfficeEventChainController;
use App\Http\Controllers\BackOffice\EventController as BackOfficeEventController;
use App\Http\Controllers\BackOffice\MemberRegistrationController as BackOfficeMemberRegistrationController;
use App\Http\Controllers\BackOffice\PostController as BackOfficePostController;
use App\Http\Controllers\BackOffice\Register;
use App\Http\Controllers\BackOffice\UserController as BackOfficeUserController;
use App\Http\Controllers\ContentController;
use App\Http\Controllers\EventController;
use App\Http\Controllers\HomeController;
use App\Http\Controllers\LocationController;
use App\Http\Controllers\MemberRegistrationController;
use App\Http\Controllers\PostController;
use App\Http\Controllers\ShootingController;
use Illuminate\Support\Facades\Route;

Route::get('/', [HomeController::class, 'index'])->name('home');

Route::get('/events', [EventController::class, 'index'])->name('events');
Route::get('/events/{id}', [EventController::class, 'show'])->name('events.show');
Route::get('/events/calendar/feed.ics', [EventController::class, 'feed_calendar'])->name('events.feed_calendar');

Route::get('/posts/{public_id}', [PostController::class, 'show'])->name('posts.show');

Route::redirect('/shootings', '/shootings/ods', 308);

Route::get('/shootings/ods', [ShootingController::class, 'indexODS'])->name('shootings');

Route::get('/shootings/field', [ShootingController::class, 'indexField'])->name('shootings.field');

Route::get('/member-registration/{form_uuid}', [MemberRegistrationController::class, 'show'])->name('member-registration.show');
Route::post('/member-registration', [MemberRegistrationController::class, 'store'])->name('member-registration.store');
Route::get('/member-registration', function () {
    abort(404);
});

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
    Route::post('events/{event}/detach', [BackOfficeEventChainController::class, 'detachEvent'])->name('bo.events.detach');

    Route::resource('event-chains', BackOfficeEventChainController::class)->names([
        'index' => 'bo.event-chains.index',
        'create' => 'bo.event-chains.create',
        'store' => 'bo.event-chains.store',
        'show' => 'bo.event-chains.show',
        'edit' => 'bo.event-chains.edit',
        'update' => 'bo.event-chains.update',
        'destroy' => 'bo.event-chains.destroy',
    ]);
    Route::post(
        'event-chains/{eventChain}/events/{event}/resolve-conflict',
        [BackOfficeEventChainController::class, 'resolveConflict']
    )->name('bo.event-chains.resolve-conflict');
    Route::post(
        'event-chains/{eventChain}/events/{event}/reset-to-chain',
        [BackOfficeEventChainController::class, 'resetToChain']
    )->name('bo.event-chains.reset-to-chain');
    Route::resource('posts', BackOfficePostController::class)->names([
        'index' => 'bo.posts.index',
        'create' => 'bo.posts.create',
        'store' => 'bo.posts.store',
        'show' => 'bo.posts.show',
        'edit' => 'bo.posts.edit',
        'update' => 'bo.posts.update',
        'destroy' => 'bo.posts.destroy',
    ]);
    Route::resource('users', BackOfficeUserController::class)->names([
        'index' => 'bo.users.index',
        'create' => 'bo.users.create',
        'store' => 'bo.users.store',
        'show' => 'bo.users.show',
        'edit' => 'bo.users.edit',
        'update' => 'bo.users.update',
        'destroy' => 'bo.users.destroy',
    ]);

    Route::middleware('permission:manage members')->prefix('dav')->group(function () {
        Route::get('/', [DavController::class, 'index'])->name('bo.dav.index');
        Route::post('/import', [DavController::class, 'importFromDav'])->name('bo.dav.import');
    });

    Route::get('member-registrations', [BackOfficeMemberRegistrationController::class, 'index'])
        ->name('bo.member-registrations.index');
    Route::get('member-registrations/{memberRegistration}', [BackOfficeMemberRegistrationController::class, 'show'])
        ->name('bo.member-registrations.show');
    Route::post('member-registrations', [BackOfficeMemberRegistrationController::class, 'store'])
        ->name('bo.member-registrations.store');
    Route::post('member-registrations/{memberRegistration}/retry', [BackOfficeMemberRegistrationController::class, 'retry'])
        ->name('bo.member-registrations.retry')
        ->middleware('permission:manage member registration');

    require __DIR__.'/settings.php';

    // ── Register Management ──
    Route::middleware('permission:manage products')->prefix('register')->group(function () {
        Route::resource('products', Register\ProductController::class)->names([
            'index' => 'bo.register.products.index',
            'create' => 'bo.register.products.create',
            'store' => 'bo.register.products.store',
            'show' => 'bo.register.products.show',
            'edit' => 'bo.register.products.edit',
            'update' => 'bo.register.products.update',
            'destroy' => 'bo.register.products.destroy',
        ]);
        Route::post('products/{product}/attach-child', [Register\ProductController::class, 'attachChild'])
            ->name('bo.register.products.attach-child');
        Route::delete('products/{product}/detach-child/{child}', [Register\ProductController::class, 'detachChild'])
            ->name('bo.register.products.detach-child');
    });

    Route::middleware('permission:manage categories')->prefix('register')->group(function () {
        Route::resource('categories', Register\CategoryController::class)->except('show')->names([
            'index' => 'bo.register.categories.index',
            'create' => 'bo.register.categories.create',
            'store' => 'bo.register.categories.store',
            'edit' => 'bo.register.categories.edit',
            'update' => 'bo.register.categories.update',
            'destroy' => 'bo.register.categories.destroy',
        ]);
    });

    Route::middleware('permission:manage vouchers')->prefix('register')->group(function () {
        Route::resource('vouchers', Register\VoucherController::class)->except('show')->names([
            'index' => 'bo.register.vouchers.index',
            'create' => 'bo.register.vouchers.create',
            'store' => 'bo.register.vouchers.store',
            'edit' => 'bo.register.vouchers.edit',
            'update' => 'bo.register.vouchers.update',
            'destroy' => 'bo.register.vouchers.destroy',
        ]);
    });

    Route::middleware('permission:manage seasons')->prefix('register')->group(function () {
        Route::get('seasons/{season}/preview-base-vouchers', [Register\SeasonController::class, 'previewBaseVouchers'])
            ->name('bo.register.seasons.preview-base-vouchers');
        Route::post('seasons/{season}/generate-base-vouchers', [Register\SeasonController::class, 'generateBaseVouchers'])
            ->name('bo.register.seasons.generate-base-vouchers');

        Route::resource('seasons', Register\SeasonController::class)->names([
            'index' => 'bo.register.seasons.index',
            'create' => 'bo.register.seasons.create',
            'store' => 'bo.register.seasons.store',
            'show' => 'bo.register.seasons.show',
            'edit' => 'bo.register.seasons.edit',
            'update' => 'bo.register.seasons.update',
            'destroy' => 'bo.register.seasons.destroy',
        ]);
    });

    Route::middleware('permission:manage members')->prefix('register')->group(function () {
        Route::resource('members', Register\MemberController::class)->only([
            'index', 'create', 'edit', 'update', 'destroy',
        ])->names([
            'index' => 'bo.register.members.index',
            'create' => 'bo.register.members.create',
            'edit' => 'bo.register.members.edit',
            'update' => 'bo.register.members.update',
            'destroy' => 'bo.register.members.destroy',
        ]);
    });

    Route::middleware('permission:manage operating days')->prefix('register')->group(function () {
        Route::resource('operating-days', Register\OperatingDayController::class)->only(['index', 'create', 'store', 'show', 'update'])->names([
            'index' => 'bo.register.operating-days.index',
            'create' => 'bo.register.operating-days.create',
            'store' => 'bo.register.operating-days.store',
            'show' => 'bo.register.operating-days.show',
            'update' => 'bo.register.operating-days.update',
        ]);
    });

    Route::middleware('permission:refund ticket')->prefix('register')->group(function () {
        Route::post('tickets/{ticket}/refund', [Register\TicketController::class, 'refund'])
            ->name('bo.register.tickets.refund');
    });

    Route::middleware('permission:view reports')->prefix('register/reports')->group(function () {
        Route::get('/', [Register\ReportController::class, 'index'])
            ->name('bo.register.reports.index');
        Route::get('day/{operatingDay}', [Register\ReportController::class, 'dayDetail'])
            ->name('bo.register.reports.day-detail');
        Route::get('day/{operatingDay}/summary', [Register\ReportController::class, 'daySummary'])
            ->name('bo.register.reports.day-summary');
        Route::get('day/{operatingDay}/balance', [Register\ReportController::class, 'dayBalance'])
            ->name('bo.register.reports.day-balance');
        Route::get('period', [Register\ReportController::class, 'period'])
            ->name('bo.register.reports.period');
        Route::get('season/{season}', [Register\ReportController::class, 'season'])
            ->name('bo.register.reports.season');
        Route::get('season/{season}/export', [Register\ReportExportController::class, 'export'])
            ->name('bo.register.reports.season-export');
    });

    Route::middleware('permission:restore register')->prefix('register')->group(function () {
        Route::get('trash', [Register\TrashController::class, 'index'])
            ->name('bo.register.trash.index');
        Route::post('trash/{type}/{id}/restore', [Register\TrashController::class, 'restore'])
            ->name('bo.register.trash.restore');
        Route::delete('trash/{type}/{id}', [Register\TrashController::class, 'forceDelete'])
            ->name('bo.register.trash.force-delete');
    });
});

require __DIR__.'/auth.php';
