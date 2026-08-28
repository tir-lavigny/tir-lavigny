# Register System — API Routes

All cashier-facing endpoints are under Sanctum auth. Admin and accountant operate through the back-office (web routes).

## Route File: `routes/api.php`

```php
<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\API\Register;

Route::middleware(['auth:sanctum', 'role:cashier|admin'])->prefix('register')->group(function () {

    // ── Day operations ──
    Route::post('/days/open', [Register\OperatingDayController::class, 'open']);
    Route::post('/days/{operatingDay}/close', [Register\OperatingDayController::class, 'close']);
    Route::post('/days/{operatingDay}/reopen', [Register\OperatingDayController::class, 'reopen']);
    Route::post('/days/{operatingDay}/cash-remark', [Register\OperatingDayController::class, 'addCashRemark']);
    Route::get('/days/current', [Register\OperatingDayController::class, 'current']);
    Route::get('/days/{operatingDay}', [Register\OperatingDayController::class, 'show']);

    // ── Ticket operations ──
    Route::post('/tickets', [Register\TicketController::class, 'store']);
    Route::get('/tickets/{ticket}', [Register\TicketController::class, 'show']);

    // ── Product / Category lookup (read-only) ──
    Route::get('/products', [Register\ProductController::class, 'index']);
    Route::get('/products/{product}', [Register\ProductController::class, 'show']);
    Route::get('/categories', [Register\CategoryController::class, 'index']);

    // ── Voucher validation ──
    Route::post('/vouchers/validate', [Register\VoucherValidationController::class, 'validate']);
});
```

## Route File: `routes/web.php`

Inside the existing `/back-office` group (already has `auth` + `verified`):

```php
Route::middleware(['auth', 'verified'])->prefix('back-office')->group(function () {

    // ── Existing routes (locations, contents, events, etc.) ──

    // ── Register Management (admin) ──
    Route::middleware('permission:manage products')->prefix('register')->group(function () {
        Route::resource('products', BackOffice\Register\ProductController::class);
        Route::post('products/{product}/attach-child', [BackOffice\Register\ProductController::class, 'attachChild']);
        Route::delete('products/{product}/detach-child/{child}', [BackOffice\Register\ProductController::class, 'detachChild']);
    });

    Route::middleware('permission:manage categories')->prefix('register')->group(function () {
        Route::resource('categories', BackOffice\Register\CategoryController::class)->except('show');
    });

    Route::middleware('permission:manage vouchers')->prefix('register')->group(function () {
        Route::resource('vouchers', BackOffice\Register\VoucherController::class)->except('show');
    });

    Route::middleware('permission:manage seasons')->prefix('register')->group(function () {
        Route::resource('seasons', BackOffice\Register\SeasonController::class);
    });

    Route::middleware('permission:manage members')->prefix('register')->group(function () {
        Route::resource('members', BackOffice\Register\MemberController::class);
    });

    Route::middleware('permission:manage operating days')->prefix('register')->group(function () {
        Route::resource('operating-days', BackOffice\Register\OperatingDayController::class)->only(['index', 'show', 'update']);
    });

    Route::middleware('permission:refund ticket')->prefix('register')->group(function () {
        Route::post('tickets/{ticket}/refund', [BackOffice\Register\TicketController::class, 'refund']);
    });

    // ── Reports (accountant) ──
    Route::middleware('permission:view reports')->prefix('register/reports')->group(function () {
        Route::get('day/{operatingDay}', [BackOffice\Register\ReportController::class, 'dayDetail']);
        Route::get('day/{operatingDay}/summary', [BackOffice\Register\ReportController::class, 'daySummary']);
        Route::get('day/{operatingDay}/balance', [BackOffice\Register\ReportController::class, 'dayBalance']);
        Route::get('period', [BackOffice\Register\ReportController::class, 'period']);
        Route::get('season/{season}', [BackOffice\Register\ReportController::class, 'season']);
    });
});
```

## Route Summary

| Method | Endpoint | Permission | Description |
|---|---|---|---|
| POST | `/api/register/days/open` | open day | Opens a new operating day |
| POST | `/api/register/days/{day}/close` | close day | Closes the day, recalculates totals |
| POST | `/api/register/days/{day}/reopen` | reopen day | Re-opens a closed day (admin) |
| POST | `/api/register/days/{day}/cash-remark` | add cash remark | Stores cash remark |
| GET | `/api/register/days/current` | view register | Returns current open day |
| GET | `/api/register/days/{day}` | view register | Day with tickets |
| POST | `/api/register/tickets` | create ticket | Creates ticket with items |
| GET | `/api/register/tickets/{ticket}` | view tickets | Ticket with items |
| GET | `/api/register/products` | add product to ticket | Lists available products |
| GET | `/api/register/products/{product}` | add product to ticket | Product detail with children |
| GET | `/api/register/categories` | add product to ticket | Lists non-restricted categories |
| POST | `/api/register/vouchers/validate` | apply voucher to ticket | Validates and returns discount |
| GET | `/back-office/register/products` | manage products | Index page |
| GET/POST | `/back-office/register/products/create` | manage products | Create product |
| GET/PUT | `/back-office/register/products/{product}/edit` | manage products | Edit product |
| DELETE | `/back-office/register/products/{product}` | manage products | Soft-delete product |
| POST | `/back-office/register/products/{product}/attach-child` | manage products | Add child to bundle |
| DELETE | `/back-office/register/products/{product}/detach-child/{child}` | manage products | Remove child from bundle |
| GET | `/back-office/register/categories` | manage categories | List categories |
| GET/POST | `/back-office/register/categories/create` | manage categories | Create category |
| GET/PUT | `/back-office/register/categories/{category}/edit` | manage categories | Edit category |
| DELETE | `/back-office/register/categories/{category}` | manage categories | Soft-delete category |
| GET | `/back-office/register/vouchers` | manage vouchers | List vouchers |
| GET/POST | `/back-office/register/vouchers/create` | manage vouchers | Create voucher |
| GET/PUT | `/back-office/register/vouchers/{voucher}/edit` | manage vouchers | Edit voucher |
| DELETE | `/back-office/register/vouchers/{voucher}` | manage vouchers | Soft-delete voucher |
| GET | `/back-office/register/seasons` | manage seasons | List seasons |
| GET/POST | `/back-office/register/seasons/create` | manage seasons | Create season |
| GET/PUT | `/back-office/register/seasons/{season}/edit` | manage seasons | Edit season |
| GET | `/back-office/register/seasons/{season}` | manage seasons | Show season |
| DELETE | `/back-office/register/seasons/{season}` | manage seasons | Soft-delete season |
| GET | `/back-office/register/members` | manage members | List members |
| GET/POST | `/back-office/register/members/create` | manage members | Create member |
| GET/PUT | `/back-office/register/members/{member}/edit` | manage members | Edit member |
| DELETE | `/back-office/register/members/{member}` | manage members | Soft-delete member |
| GET | `/back-office/register/operating-days` | manage operating days | List days |
| GET | `/back-office/register/operating-days/{day}` | manage operating days | Show day with tickets |
| PUT | `/back-office/register/operating-days/{day}` | manage operating days | Edit cash_remark/balance |
| POST | `/back-office/register/tickets/{ticket}/refund` | refund ticket | Soft-delete (refund) ticket |
| GET | `/back-office/register/reports/day/{day}` | view reports | Day detail (all items) |
| GET | `/back-office/register/reports/day/{day}/summary` | view reports | Day summary (tickets only) |
| GET | `/back-office/register/reports/day/{day}/balance` | view reports | Day balance (totals only) |
| GET | `/back-office/register/reports/period` | view reports | Period report (?from=&to=&detail=) |
| GET | `/back-office/register/reports/season/{season}` | view reports | Season report |
