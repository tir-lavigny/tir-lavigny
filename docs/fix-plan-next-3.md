# Fix Plan: Next 3 Architecture Issues

Issues: N+1 queries (#4), Database schema (#8), No third-party boundary (#9).

---

## Phase 1: N+1 queries

Goal: Fix eager loading issues and redundant queries.

### 1.1 Fix UserResource — use `whenLoaded()`

**Problem**: `UserResource::toArray()` accesses `$this->roles` without `whenLoaded()` guard. Fires query if not eager-loaded.

**File**: `app/Http/Resources/UserResource.php`

**Change**:
```php
'roles' => RoleResource::collection($this->whenLoaded('roles')),
```

### 1.2 Fix RoleResource — use `whenLoaded()`

**Problem**: `RoleResource::toArray()` accesses `$this->permissions` without `whenLoaded()` guard.

**File**: `app/Http/Resources/RoleResource.php`

**Change**:
```php
'permissions' => PermissionResource::collection($this->whenLoaded('permissions')),
```

### 1.3 Fix AuthController::me() — eager load roles

**Problem**: `me()` creates `UserResource` without eager loading roles. N+1 chain on every call.

**File**: `app/Http/Controllers/API/AuthController.php`

**Change**:
```php
public function me(Request $request): JsonResponse
{
    $user = $request->user()->load('roles.permissions');
    return (new UserResource($user))->response();
}
```

### 1.4 Fix OperatingDayController::show() — remove redundant double-query

**Problem**: `show()` loads tickets via `->load()`, then re-fetches them via `->tickets()->withTrashed()->get()`.

**File**: `app/Http/Controllers/API/Register/OperatingDayController.php`

**Change**: Remove the `->load()` call, keep only the `->tickets()->withTrashed()->with(...)->get()` call:
```php
public function show(OperatingDay $operatingDay): JsonResponse
{
    $operatingDay->setRelation('tickets', $operatingDay->tickets()->withTrashed()
        ->with(['ticketItems.product', 'ticketItems.voucher'])
        ->get());

    return response()->json(['data' => new OperatingDayResource($operatingDay)]);
}
```

### 1.5 Fix TicketController::show() — remove redundant double-query

**Problem**: Same double-query pattern as OperatingDayController.

**File**: `app/Http/Controllers/API/Register/TicketController.php`

**Change**: Remove the `->load()` call:
```php
public function show(Ticket $ticket): JsonResponse
{
    $allItems = $ticket->ticketItems()->withTrashed()
        ->with(['product', 'voucher'])
        ->get();

    $ticket->setRelation('ticketItems', $allItems);

    return response()->json(['data' => $ticket]);
}
```

### 1.6 Fix ReportController::dayDetail() — remove redundant double-query

**Problem**: Same double-query pattern.

**File**: `app/Http/Controllers/BackOffice/Register/ReportController.php`

**Change**: Remove the `->load()` call:
```php
public function dayDetail(OperatingDay $operatingDay): Response
{
    $operatingDay->tickets = $operatingDay->tickets()->withTrashed()
        ->with(['ticketItems.product', 'ticketItems.voucher'])
        ->get();

    return Inertia::render('backOffice/Register/Reports/DayDetail', [
        'operatingDay' => $operatingDay,
    ]);
}
```

### Tests

- Existing tests should still pass (behavior unchanged).
- No new tests needed — these are mechanical eager-loading fixes.

---

## Phase 2: Database schema

Goal: Add missing indexes, document implications.

### 2.1 Add indexes to activity_logs table

**Problem**: `activity_logs` has no indexes on `model`, `model_id`, `action`, or `user_id`. Will be catastrophic at scale.

**File**: `database/migrations/2026_XX_XX_add_indexes_to_activity_logs.php` (new)

**Migration**:
```php
Schema::table('activity_logs', function (Blueprint $table) {
    $table->index(['model', 'model_id']);
    $table->index('action');
    $table->index('user_id');
});
```

Follow migration policy: check `Schema::hasTable`, separate blocks, conditional drops in `down()`.

### 2.2 Document soft delete implications

No code change needed. Add a comment in `app/Models/OperatingDay.php` noting that hard-deleting cascades to soft-deleted tickets.

---

## Phase 3: Third-party boundary

Goal: Introduce interface for InfomaniakDavService so tests can swap implementations and future provider changes are localized.

### 3.1 Create DavServiceInterface

**File**: `app/Services/DavServiceInterface.php` (new)

**Methods to include** (based on actual usage in codebase):
- `getAddressbookUrl(): string`
- `getContacts(): array`
- `getContact(string $url): ?array`
- `createContactFromMemberRegistration(MemberRegistration $registration): void`
- `updateMember(Member $member): void`
- `softDeleteMember(Member $member): void`
- `restoreMember(Member $member): void`
- `deleteMember(Member $member): void`

### 3.2 Implement interface on InfomaniakDavService

**File**: `app/Services/InfomaniakDavService.php`

Add `implements DavServiceInterface` to the class declaration.

### 3.3 Update AppServiceProvider binding

**File**: `app/Providers/AppServiceProvider.php`

Change singleton binding:
```php
$this->app->singleton(DavServiceInterface::class, InfomaniakDavService::class);
```

### 3.4 Update DavSyncService to depend on interface

**File**: `app/Services/DavSyncService.php`

Change constructor parameter type:
```php
public function __construct(
    private readonly DavServiceInterface $dav
) {}
```

### 3.5 Update DavController index method

**File**: `app/Http/Controllers/BackOffice/DavController.php`

The `index` method still type-hints `InfomaniakDavService`. Change to `DavServiceInterface`.

### 3.6 Update callers in MemberController and TrashController

**Files**:
- `app/Http/Controllers/BackOffice/Register/MemberController.php`
- `app/Http/Controllers/BackOffice/Register/TrashController.php`

These inject `InfomaniakDavService` via method injection. Change to `DavServiceInterface`.

### Tests

- Update existing tests to mock `DavServiceInterface` instead of concrete class.
- Feature tests in `DavControllerTest` already mock the concrete class — update to mock the interface.

---

## Execution order

1. Phase 1 (N+1 queries) — smallest, no dependencies
2. Phase 3 (third-party boundary) — isolated to DAV-related code
3. Phase 2 (database schema) — migration only, no code dependencies

Each phase should be a separate commit.

---

## Risks

- **Phase 1**: Removing `whenLoaded()` guard means resources silently return empty array if relationship not loaded. Existing tests cover this.
- **Phase 2**: New migration adds indexes. MariaDB locks table during index creation on large tables. Low risk for this app's scale.
- **Phase 3**: Interface introduction requires updating all injection sites. Tests already mock the concrete class, so they need updating.
