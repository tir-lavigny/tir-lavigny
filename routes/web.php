<?php

use App\Http\Controllers\BackOffice\DashboardController;
use App\Http\Controllers\BackOffice\DavController;
use App\Http\Controllers\BackOffice\EventChainController as BackOfficeEventChainController;
use App\Http\Controllers\BackOffice\EventController as BackOfficeEventController;
use App\Http\Controllers\BackOffice\Invoicing\BillingCustomerController as BackOfficeBillingCustomerController;
use App\Http\Controllers\BackOffice\Invoicing\CreditNoteController as BackOfficeCreditNoteController;
use App\Http\Controllers\BackOffice\Invoicing\FinancialDocumentController as BackOfficeFinancialDocumentController;
use App\Http\Controllers\BackOffice\Invoicing\InvoiceController as BackOfficeInvoiceController;
use App\Http\Controllers\BackOffice\Invoicing\InvoiceDeliveryController as BackOfficeInvoiceDeliveryController;
use App\Http\Controllers\BackOffice\Invoicing\InvoicingSettingsController as BackOfficeInvoicingSettingsController;
use App\Http\Controllers\BackOffice\Invoicing\MembershipFeeCampaignController as BackOfficeMembershipFeeCampaignController;
use App\Http\Controllers\BackOffice\Invoicing\MembershipFeeTypeController as BackOfficeMembershipFeeTypeController;
use App\Http\Controllers\BackOffice\Invoicing\PaymentController as BackOfficePaymentController;
use App\Http\Controllers\BackOffice\MemberController;
use App\Http\Controllers\BackOffice\MemberExportController;
use App\Http\Controllers\BackOffice\MemberRegistrationController as BackOfficeMemberRegistrationController;
use App\Http\Controllers\BackOffice\PostController as BackOfficePostController;
use App\Http\Controllers\BackOffice\Register;
use App\Http\Controllers\BackOffice\Stock\StockCountController;
use App\Http\Controllers\BackOffice\Stock\StockLocationController;
use App\Http\Controllers\BackOffice\Stock\StockLossController;
use App\Http\Controllers\BackOffice\Stock\StockTransferController;
use App\Http\Controllers\BackOffice\UserController as BackOfficeUserController;
use App\Http\Controllers\BecomeMemberController;
use App\Http\Controllers\ContentController;
use App\Http\Controllers\EventController;
use App\Http\Controllers\HomeController;
use App\Http\Controllers\LocationController;
use App\Http\Controllers\MemberRegistrationController;
use App\Http\Controllers\PostController;
use App\Http\Controllers\PrivacyPolicyController;
use App\Http\Controllers\ShootingController;
use App\Http\Controllers\ShootingInitiationController;
use Illuminate\Support\Facades\Route;

Route::get('/', [HomeController::class, 'index'])->name('home');

Route::get('/become-member', BecomeMemberController::class)->name('become-member');

Route::get('/shooting-initiation', ShootingInitiationController::class)->name('shooting-initiation');

Route::get('/privacy-policy', PrivacyPolicyController::class)->name('privacy-policy');

Route::get('/events', [EventController::class, 'index'])->name('events');
Route::get('/events/{event}', [EventController::class, 'show'])->name('events.show');
Route::get('/events/calendar/feed.ics', [EventController::class, 'feed_calendar'])->name('events.feed_calendar');

Route::get('/posts/{public_id}', [PostController::class, 'show'])->name('posts.show');

Route::redirect('/shootings', '/shootings/ods', 308);

Route::get('/shootings/ods', [ShootingController::class, 'indexODS'])->name('shootings');

Route::get('/shootings/field', [ShootingController::class, 'indexField'])->name('shootings.field');

Route::get('/member-registration/{form_uuid}', [MemberRegistrationController::class, 'show'])->name('member-registration.show');
Route::post('/member-registration', [MemberRegistrationController::class, 'store'])->name('member-registration.store');
Route::post('/member-registration/verify', [MemberRegistrationController::class, 'verify'])
    ->middleware('throttle:member-registration-verify-email')
    ->name('member-registration.verify');
Route::post('/member-registration/resend-verification', [MemberRegistrationController::class, 'resendVerification'])
    ->middleware('throttle:member-registration-resend-verification')
    ->name('member-registration.resend-verification');
Route::get('/member-registration', function () {
    abort(404);
});

Route::prefix('back-office')->middleware(['auth', 'verified', 'permission:access backoffice'])->group(function () {
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
        Route::get('/sync/preview', [DavController::class, 'syncPreview'])->name('bo.dav.sync.preview');
        Route::post('/sync', [DavController::class, 'sync'])->name('bo.dav.sync');
        Route::post('/link', [DavController::class, 'link'])->name('bo.dav.link');
    });

    Route::get('member-registrations', [BackOfficeMemberRegistrationController::class, 'index'])
        ->name('bo.member-registrations.index');
    Route::get('member-registrations/{memberRegistration}', [BackOfficeMemberRegistrationController::class, 'show'])
        ->name('bo.member-registrations.show');
    Route::post('member-registrations', [BackOfficeMemberRegistrationController::class, 'store'])
        ->name('bo.member-registrations.store');
    Route::patch('member-registrations/{memberRegistration}/avs', [BackOfficeMemberRegistrationController::class, 'updateAvs'])
        ->name('bo.member-registrations.update-avs')
        ->middleware('permission:manage member registration');
    Route::post('member-registrations/{memberRegistration}/validate', [BackOfficeMemberRegistrationController::class, 'validate'])
        ->name('bo.member-registrations.validate')
        ->middleware('permission:manage member registration');
    Route::post('member-registrations/{memberRegistration}/retry', [BackOfficeMemberRegistrationController::class, 'retry'])
        ->name('bo.member-registrations.retry')
        ->middleware('permission:manage member registration');
    Route::delete('member-registrations/{memberRegistration}', [BackOfficeMemberRegistrationController::class, 'destroy'])
        ->name('bo.member-registrations.destroy')
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

        Route::resource('price-lists', Register\PriceListController::class)->except('show')->names([
            'index' => 'bo.register.price-lists.index',
            'create' => 'bo.register.price-lists.create',
            'store' => 'bo.register.price-lists.store',
            'edit' => 'bo.register.price-lists.edit',
            'update' => 'bo.register.price-lists.update',
            'destroy' => 'bo.register.price-lists.destroy',
        ]);
        Route::get('price-lists/{price_list}/preview', [Register\PriceListController::class, 'preview'])
            ->name('bo.register.price-lists.preview');
        Route::get('price-lists/{price_list}/apply', [Register\PriceListController::class, 'applyPreview'])
            ->name('bo.register.price-lists.apply.preview');
        Route::post('price-lists/{price_list}/apply', [Register\PriceListController::class, 'apply'])
            ->name('bo.register.price-lists.apply');
        Route::post('price-lists/{price_list}/sync-from-catalog', [Register\PriceListController::class, 'syncFromCatalog'])
            ->name('bo.register.price-lists.sync-from-catalog');
        Route::post('price-lists/{price_list}/sync-prices', [Register\PriceListController::class, 'syncPrices'])
            ->name('bo.register.price-lists.sync-prices');
        Route::post('price-lists/{price_list}/duplicate', [Register\PriceListController::class, 'duplicate'])
            ->name('bo.register.price-lists.duplicate');
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

    Route::middleware('permission:manage cash movements')->prefix('register')->group(function () {
        Route::post('seasons/{season}/cash-movements', [Register\CashMovementController::class, 'store'])
            ->name('bo.register.seasons.cash-movements.store');
        Route::put('seasons/{season}/cash-movements/{cashMovement}', [Register\CashMovementController::class, 'update'])
            ->name('bo.register.seasons.cash-movements.update');
        Route::delete('seasons/{season}/cash-movements/{cashMovement}', [Register\CashMovementController::class, 'destroy'])
            ->name('bo.register.seasons.cash-movements.destroy');
    });

    Route::middleware('permission:manage operating days')->prefix('register')->group(function () {
        Route::post('operating-days/{operatingDay}/corrections', [Register\OperatingDayController::class, 'storeCorrection'])
            ->name('bo.register.operating-days.corrections.store');
    });

    Route::middleware('permission:manage members')->group(function () {
        Route::get('members/export', [MemberExportController::class, 'export'])
            ->name('bo.members.export');
        Route::resource('members', MemberController::class)->only([
            'index', 'create', 'store', 'show', 'edit', 'update',
        ])->withTrashed()->names([
            'index' => 'bo.members.index',
            'create' => 'bo.members.create',
            'store' => 'bo.members.store',
            'show' => 'bo.members.show',
            'edit' => 'bo.members.edit',
            'update' => 'bo.members.update',
        ]);
        Route::delete('members/{member}', [MemberController::class, 'destroy'])->withTrashed()->name('bo.members.destroy');
        Route::post('members/{member}/restore', [MemberController::class, 'restore'])->withTrashed()->name('bo.members.restore');
        Route::delete('members/{member}/force-delete', [MemberController::class, 'forceDelete'])->withTrashed()->name('bo.members.force-delete');
        Route::post('members/{member}/sync', [MemberController::class, 'sync'])->withTrashed()->name('bo.members.sync');
        Route::delete('members/{member}/unused-vouchers', [MemberController::class, 'destroyUnusedVouchers'])
            ->withTrashed()
            ->name('bo.members.destroy-unused-vouchers');
        Route::post('members/{member}/retry-dav-delete', [MemberController::class, 'retryDavDelete'])
            ->withTrashed()
            ->name('bo.members.retry-dav-delete');
    });

    Route::middleware('permission:manage operating days')->prefix('register')->group(function () {
        Route::resource('operating-days', Register\OperatingDayController::class)->only(['index', 'create', 'store', 'show', 'update'])->names([
            'index' => 'bo.register.operating-days.index',
            'create' => 'bo.register.operating-days.create',
            'store' => 'bo.register.operating-days.store',
            'show' => 'bo.register.operating-days.show',
            'update' => 'bo.register.operating-days.update',
        ]);

        Route::get('tickets/{ticket}', [Register\TicketController::class, 'show'])
            ->name('bo.register.tickets.show');
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

    Route::prefix('invoicing')->name('bo.invoicing.')->group(function () {
        Route::middleware('permission:manage invoicing')->group(function () {
            Route::get('settings', [BackOfficeInvoicingSettingsController::class, 'edit'])
                ->name('settings.edit');
            Route::put('settings', [BackOfficeInvoicingSettingsController::class, 'update'])
                ->name('settings.update');
        });

        Route::middleware('permission:view invoices|manage invoicing|manage membership fees')->group(function () {
            Route::get('membership-fee-types', [BackOfficeMembershipFeeTypeController::class, 'index'])
                ->name('membership-fee-types.index');
            Route::get('membership-fee-campaigns', [BackOfficeMembershipFeeCampaignController::class, 'index'])
                ->name('membership-fee-campaigns.index');
            Route::get('membership-fee-campaigns/{membershipFeeCampaign}', [BackOfficeMembershipFeeCampaignController::class, 'show'])
                ->whereNumber('membershipFeeCampaign')
                ->name('membership-fee-campaigns.show');
        });

        Route::middleware('permission:manage membership fees|manage invoicing')->group(function () {
            Route::get('membership-fee-types/create', [BackOfficeMembershipFeeTypeController::class, 'create'])
                ->name('membership-fee-types.create');
            Route::post('membership-fee-types', [BackOfficeMembershipFeeTypeController::class, 'store'])
                ->name('membership-fee-types.store');
            Route::get('membership-fee-types/{membershipFeeType}/edit', [BackOfficeMembershipFeeTypeController::class, 'edit'])
                ->whereNumber('membershipFeeType')
                ->name('membership-fee-types.edit');
            Route::put('membership-fee-types/{membershipFeeType}', [BackOfficeMembershipFeeTypeController::class, 'update'])
                ->whereNumber('membershipFeeType')
                ->name('membership-fee-types.update');

            Route::get('membership-fee-campaigns/create', [BackOfficeMembershipFeeCampaignController::class, 'create'])
                ->name('membership-fee-campaigns.create');
            Route::post('membership-fee-campaigns', [BackOfficeMembershipFeeCampaignController::class, 'store'])
                ->name('membership-fee-campaigns.store');
            Route::get('membership-fee-campaigns/{membershipFeeCampaign}/edit', [BackOfficeMembershipFeeCampaignController::class, 'edit'])
                ->whereNumber('membershipFeeCampaign')
                ->name('membership-fee-campaigns.edit');
            Route::put('membership-fee-campaigns/{membershipFeeCampaign}', [BackOfficeMembershipFeeCampaignController::class, 'update'])
                ->whereNumber('membershipFeeCampaign')
                ->name('membership-fee-campaigns.update');
            Route::post('membership-fee-campaigns/{membershipFeeCampaign}/preview', [BackOfficeMembershipFeeCampaignController::class, 'preview'])
                ->whereNumber('membershipFeeCampaign')
                ->name('membership-fee-campaigns.preview');
            Route::post('membership-fee-campaigns/{membershipFeeCampaign}/validate', [BackOfficeMembershipFeeCampaignController::class, 'validateCampaign'])
                ->whereNumber('membershipFeeCampaign')
                ->name('membership-fee-campaigns.validate');
            Route::post('membership-fee-campaigns/{membershipFeeCampaign}/invoices/generate', [BackOfficeMembershipFeeCampaignController::class, 'generateInvoices'])
                ->whereNumber('membershipFeeCampaign')
                ->name('membership-fee-campaigns.invoices.generate');
            Route::put('membership-fee-campaigns/{membershipFeeCampaign}/members/{campaignMember}/discount', [BackOfficeMembershipFeeCampaignController::class, 'updateDiscount'])
                ->whereNumber('membershipFeeCampaign')
                ->whereNumber('campaignMember')
                ->name('membership-fee-campaigns.members.discount');
            Route::put('membership-fee-campaigns/{membershipFeeCampaign}/members/{campaignMember}/delivery', [BackOfficeMembershipFeeCampaignController::class, 'updateDelivery'])
                ->whereNumber('membershipFeeCampaign')
                ->whereNumber('campaignMember')
                ->name('membership-fee-campaigns.members.delivery');
            Route::post('membership-fee-campaigns/{membershipFeeCampaign}/members/{campaignMember}/validate', [BackOfficeMembershipFeeCampaignController::class, 'validateMember'])
                ->whereNumber('membershipFeeCampaign')
                ->whereNumber('campaignMember')
                ->name('membership-fee-campaigns.members.validate');
            Route::post('membership-fee-campaigns/{membershipFeeCampaign}/members/{campaignMember}/exclude', [BackOfficeMembershipFeeCampaignController::class, 'excludeMember'])
                ->whereNumber('membershipFeeCampaign')
                ->whereNumber('campaignMember')
                ->name('membership-fee-campaigns.members.exclude');
            Route::post('membership-fee-campaigns/{membershipFeeCampaign}/members/{campaignMember}/include', [BackOfficeMembershipFeeCampaignController::class, 'includeMember'])
                ->whereNumber('membershipFeeCampaign')
                ->whereNumber('campaignMember')
                ->name('membership-fee-campaigns.members.include');
        });

        Route::post('membership-fee-campaigns/{membershipFeeCampaign}/invoices/issue', [BackOfficeMembershipFeeCampaignController::class, 'issueInvoices'])
            ->whereNumber('membershipFeeCampaign')
            ->middleware('permission:issue invoices|manage invoicing')
            ->name('membership-fee-campaigns.invoices.issue');

        Route::middleware('permission:view invoices|manage invoicing')->group(function () {
            Route::get('billing-customers', [BackOfficeBillingCustomerController::class, 'index'])
                ->name('billing-customers.index');
        });

        Route::middleware('permission:manage invoicing')->group(function () {
            Route::get('billing-customers/create', [BackOfficeBillingCustomerController::class, 'create'])
                ->name('billing-customers.create');
            Route::post('billing-customers', [BackOfficeBillingCustomerController::class, 'store'])
                ->name('billing-customers.store');
            Route::post('billing-customers/sync-member', [BackOfficeBillingCustomerController::class, 'syncMember'])
                ->name('billing-customers.sync-member');
            Route::get('billing-customers/{billingCustomer}/edit', [BackOfficeBillingCustomerController::class, 'edit'])
                ->name('billing-customers.edit');
            Route::put('billing-customers/{billingCustomer}', [BackOfficeBillingCustomerController::class, 'update'])
                ->name('billing-customers.update');
            Route::delete('billing-customers/{billingCustomer}', [BackOfficeBillingCustomerController::class, 'destroy'])
                ->name('billing-customers.destroy');
            Route::post('billing-customers/{billingCustomer}/sync-member', [BackOfficeBillingCustomerController::class, 'syncExisting'])
                ->name('billing-customers.sync');
        });

        Route::middleware('permission:view invoices|manage invoicing')->group(function () {
            Route::get('invoices', [BackOfficeInvoiceController::class, 'index'])->name('invoices.index');
        });

        Route::middleware('permission:create invoices|manage invoicing')->group(function () {
            Route::get('invoices/create', [BackOfficeInvoiceController::class, 'create'])->name('invoices.create');
            Route::post('invoices', [BackOfficeInvoiceController::class, 'store'])->name('invoices.store');
            Route::post('invoices/preview', [BackOfficeInvoiceController::class, 'preview'])->name('invoices.preview');
            Route::get('invoices/{invoice}/edit', [BackOfficeInvoiceController::class, 'edit'])->name('invoices.edit');
            Route::put('invoices/{invoice}', [BackOfficeInvoiceController::class, 'update'])->name('invoices.update');
            Route::delete('invoices/{invoice}', [BackOfficeInvoiceController::class, 'destroy'])->name('invoices.destroy');
        });

        Route::middleware('permission:view invoices|manage invoicing')->group(function () {
            Route::get('invoices/{invoice}', [BackOfficeInvoiceController::class, 'show'])->name('invoices.show');
            Route::get('invoices/{invoice}/pdf', [BackOfficeInvoiceController::class, 'downloadPdf'])->name('invoices.pdf');
        });

        Route::post('invoices/{invoice}/issue', [BackOfficeInvoiceController::class, 'issue'])
            ->middleware('permission:issue invoices|manage invoicing')
            ->name('invoices.issue');

        Route::post('invoices/{invoice}/cancel', [BackOfficeInvoiceController::class, 'cancel'])
            ->middleware('permission:cancel invoices|manage invoicing')
            ->name('invoices.cancel');

        Route::middleware('permission:view invoices|send invoices|manage invoicing')->group(function () {
            Route::get('deliveries/email', [BackOfficeInvoiceDeliveryController::class, 'emailQueue'])
                ->name('deliveries.email.index');
            Route::get('deliveries/postal', [BackOfficeInvoiceDeliveryController::class, 'postalQueue'])
                ->name('deliveries.postal.index');
        });

        Route::middleware('permission:view payments|manage invoicing')->group(function () {
            Route::get('payments', [BackOfficePaymentController::class, 'index'])->name('payments.index');
            Route::get('payments/{payment}', [BackOfficePaymentController::class, 'show'])
                ->whereNumber('payment')
                ->name('payments.show');
        });

        Route::middleware('permission:create payments|manage invoicing')->group(function () {
            Route::get('payments/create', [BackOfficePaymentController::class, 'create'])->name('payments.create');
            Route::post('payments', [BackOfficePaymentController::class, 'store'])->name('payments.store');
            Route::get('payments/{payment}/edit', [BackOfficePaymentController::class, 'edit'])
                ->whereNumber('payment')
                ->name('payments.edit');
            Route::put('payments/{payment}', [BackOfficePaymentController::class, 'update'])
                ->whereNumber('payment')
                ->name('payments.update');
            Route::post('payments/{payment}/void', [BackOfficePaymentController::class, 'void'])
                ->whereNumber('payment')
                ->name('payments.void');
            Route::post('payments/{payment}/allocations', [BackOfficePaymentController::class, 'allocate'])
                ->whereNumber('payment')
                ->name('payments.allocations.store');
            Route::post('invoice-payments/{invoicePayment}/reverse', [BackOfficePaymentController::class, 'reverseAllocation'])
                ->whereNumber('invoicePayment')
                ->name('payments.allocations.reverse');
            Route::post('invoices/{invoice}/payments', [BackOfficePaymentController::class, 'storeForInvoice'])
                ->name('invoices.payments.store');
        });

        Route::middleware('permission:send invoices|manage invoicing')->group(function () {
            Route::post('deliveries/email', [BackOfficeInvoiceDeliveryController::class, 'sendBulkEmail'])
                ->name('deliveries.email.send');
            Route::post('deliveries/postal/print', [BackOfficeInvoiceDeliveryController::class, 'printPostal'])
                ->name('deliveries.postal.print');
            Route::post('invoices/{invoice}/email', [BackOfficeInvoiceDeliveryController::class, 'sendEmail'])
                ->name('invoices.email');
            Route::post('invoices/{invoice}/print', [BackOfficeInvoiceDeliveryController::class, 'printInvoice'])
                ->name('invoices.print');
            Route::put('invoices/{invoice}/delivery-method', [BackOfficeInvoiceDeliveryController::class, 'updateDeliveryMethod'])
                ->name('invoices.delivery-method');
        });

        Route::middleware('permission:view invoices|manage invoicing')->group(function () {
            Route::get('credit-notes', [BackOfficeCreditNoteController::class, 'index'])->name('credit-notes.index');
        });

        Route::middleware('permission:create invoices|manage invoicing')->group(function () {
            Route::get('credit-notes/create', [BackOfficeCreditNoteController::class, 'create'])->name('credit-notes.create');
            Route::post('credit-notes/preview', [BackOfficeCreditNoteController::class, 'preview'])->name('credit-notes.preview');
            Route::post('credit-notes', [BackOfficeCreditNoteController::class, 'store'])->name('credit-notes.store');
            Route::get('credit-notes/{creditNote}/edit', [BackOfficeCreditNoteController::class, 'edit'])->name('credit-notes.edit');
            Route::put('credit-notes/{creditNote}', [BackOfficeCreditNoteController::class, 'update'])->name('credit-notes.update');
            Route::delete('credit-notes/{creditNote}', [BackOfficeCreditNoteController::class, 'destroy'])->name('credit-notes.destroy');
        });

        Route::middleware('permission:view invoices|manage invoicing')->group(function () {
            Route::get('credit-notes/{creditNote}', [BackOfficeCreditNoteController::class, 'show'])->name('credit-notes.show');
            Route::get('credit-notes/{creditNote}/pdf', [BackOfficeCreditNoteController::class, 'downloadPdf'])->name('credit-notes.pdf');
            Route::get('credit-note-reversals/{creditNoteReversal}/pdf', [BackOfficeCreditNoteController::class, 'downloadReversalPdf'])->name('credit-note-reversals.pdf');
        });

        Route::post('credit-notes/{creditNote}/issue', [BackOfficeCreditNoteController::class, 'issue'])
            ->middleware('permission:issue invoices|manage invoicing')
            ->name('credit-notes.issue');

        Route::middleware('permission:manage invoicing')->group(function () {
            Route::post('credit-notes/{creditNote}/apply', [BackOfficeCreditNoteController::class, 'apply'])
                ->name('credit-notes.apply');
            Route::post('credit-note-applications/{creditNoteApplication}/reverse', [BackOfficeCreditNoteController::class, 'reverseApplication'])
                ->name('credit-note-applications.reverse');
            Route::post('credit-notes/{creditNote}/refund', [BackOfficeCreditNoteController::class, 'refund'])
                ->name('credit-notes.refund');
            Route::get('credit-notes/{creditNote}/reversals/create', [BackOfficeCreditNoteController::class, 'createReversal'])
                ->name('credit-notes.reversals.create');
            Route::post('credit-notes/{creditNote}/reversals', [BackOfficeCreditNoteController::class, 'storeReversal'])
                ->name('credit-notes.reversals.store');
            Route::post('credit-note-reversals/{creditNoteReversal}/issue', [BackOfficeCreditNoteController::class, 'issueReversal'])
                ->name('credit-note-reversals.issue');
            Route::post('financial-documents/{financialDocument}/retry', [BackOfficeFinancialDocumentController::class, 'retry'])
                ->name('financial-documents.retry');
        });
    });

    // ── Stock Management ──
    Route::middleware('permission:manage stock')->prefix('stock')->group(function () {
        Route::get('counts', [StockCountController::class, 'index'])
            ->name('bo.stock.counts.index');
        Route::get('counts/create', [StockCountController::class, 'create'])
            ->name('bo.stock.counts.create');
        Route::post('counts', [StockCountController::class, 'store'])
            ->name('bo.stock.counts.store');
        Route::get('counts/{stockCount}', [StockCountController::class, 'show'])
            ->name('bo.stock.counts.show');
        Route::post('counts/{stockCount}/complete', [StockCountController::class, 'complete'])
            ->name('bo.stock.counts.complete');

        Route::get('transfers', [StockTransferController::class, 'index'])
            ->name('bo.stock.transfers.index');
        Route::get('transfers/create', [StockTransferController::class, 'create'])
            ->name('bo.stock.transfers.create');
        Route::post('locations/{fromLocation}/transfers', [StockTransferController::class, 'store'])
            ->name('bo.stock.transfers.store');
        Route::get('transfers/{stockTransfer}', [StockTransferController::class, 'show'])
            ->name('bo.stock.transfers.show');

        Route::get('losses', [StockLossController::class, 'index'])
            ->name('bo.stock.losses.index');
        Route::get('losses/create', [StockLossController::class, 'create'])
            ->name('bo.stock.losses.create');
        Route::post('locations/{location}/losses', [StockLossController::class, 'store'])
            ->name('bo.stock.losses.store');
        Route::get('losses/{stockMovement}', [StockLossController::class, 'show'])
            ->name('bo.stock.losses.show');

        Route::get('locations', [StockLocationController::class, 'index'])
            ->name('bo.stock.locations.index');
        Route::get('locations/create', [StockLocationController::class, 'create'])
            ->name('bo.stock.locations.create');
        Route::post('locations', [StockLocationController::class, 'store'])
            ->name('bo.stock.locations.store');
        Route::get('locations/{stockLocation}', [StockLocationController::class, 'show'])
            ->name('bo.stock.locations.show');
        Route::get('locations/{stockLocation}/edit', [StockLocationController::class, 'edit'])
            ->name('bo.stock.locations.edit');
        Route::put('locations/{stockLocation}', [StockLocationController::class, 'update'])
            ->name('bo.stock.locations.update');
        Route::post('locations/{stockLocation}/compact', [StockLocationController::class, 'compact'])
            ->name('bo.stock.locations.compact');
    });
});

require __DIR__.'/auth.php';
