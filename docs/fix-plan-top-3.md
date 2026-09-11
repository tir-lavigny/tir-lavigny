# Fix Plan: Top 3 Architecture Mistakes

Priority order: Fat controllers, Validation in wrong layer, Synchronous everything.

---

## Phase 1: Fat controllers → Service extraction

Goal: Move domain logic out of controllers into services. Extract validation where needed.

### 1.1 Extract `SeasonVoucherService`

**Problem**: `BackOffice/Register/SeasonController` has 4 private methods that are pure domain logic.

**File**: `app/Services/SeasonVoucherService.php` (new)

**Methods to extract**:
- `getBasePackage(): array` — returns product-to-count mapping
- `resolveProducts(array $package): Collection` — queries products by name, aborts if not found
- `computePlan(Season $season, Collection $products): array` — computes voucher plan from existing vouchers vs required counts
- `generateBaseVouchers(Season $season, array $plan): void` — DB transaction, creates Voucher records

**Controller changes**:
- `SeasonController::previewBaseVouchers()` → calls `$service->computePlan($season, $service->resolveProducts($package))`
- `SeasonController::generateBaseVouchers()` → calls `$service->generateBaseVouchers($season, $package)`

**Tests**: Add `SeasonVoucherServiceTest` covering plan computation and voucher generation.

### 1.2 Extract `DavSyncService`

**Problem**: `BackOffice/DavController` has 3 methods with 37-50 lines of sync/import logic.

**File**: `app/Services/DavSyncService.php` (new)

**Methods to extract**:
- `importFromDav(Collection $contacts): array` — filters, parses vCard, creates Member records
- `syncPreview(Collection $contacts): array` — builds diff pairs comparing DAV and member names
- `sync(array $updates): void` — updates members or DAV contacts

**Controller changes**:
- `DavController::importFromDav()` → calls `$service->importFromDav($contacts)`
- `DavController::syncPreview()` → calls `$service->syncPreview($contacts)`
- `DavController::sync()` → calls `$service->sync($updates)`

**Tests**: Add `DavSyncServiceTest` with mock `InfomaniakDavService`.

### 1.3 Extract `EventService`

**Problem**: `BackOffice/EventController::update()` has 62 lines with chain-override comparison logic.

**File**: `app/Services/EventService.php` (new)

**Methods to extract**:
- `compareEventToChain(Event $event, array $data): array` — returns changed fields comparing event vs chain
- `applyChainOverrides(Event $event, array $changedFields): void` — marks/unmarks fields as overridden

**Controller changes**:
- `EventController::update()` → calls `$service->compareEventToChain()` and `$service->applyChainOverrides()`
- `EventController::store()` → calls `$service` for content creation logic

**Tests**: Add `EventServiceTest` covering chain override detection.

### 1.4 Add FormRequest classes

**Problem**: Several controllers use inline `$request->validate()`.

**New FormRequest classes**:
- `app/Http/Requests/BackOffice/StoreEventRequest.php` — validation rules for `EventController::store()`
- `app/Http/Requests/BackOffice/UpdateEventRequest.php` — validation rules for `EventController::update()`
- `app/Http/Requests/BackOffice/StoreDavSyncRequest.php` — validation rules for `DavController::sync()`
- `app/Http/Requests/BackOffice/StoreDavImportRequest.php` — validation rules for `DavController::importFromDav()`

**Controller changes**: Replace inline `$request->validate()` with FormRequest injection.

### 1.5 Extract email logic from `OperatingDayController`

**Problem**: `API/Register/OperatingDayController::update()` line 50 sends email inline.

**File**: `app/Jobs/SendOperatingDayClosedEmail.php` (new, implements `ShouldQueue`)

**Controller changes**: Replace `Mail::to(...)->send(new OperatingDayClosed(...))` with `dispatch(new SendOperatingDayClosedEmail($operatingDay))`.

**Tests**: Add job test.

---

## Phase 2: Validation in wrong layer

Goal: Move business rule validation from FormRequests to service/action classes.

### 2.1 Extract ticket validation logic

**Problem**: `StoreTicketRequest::withValidator()` and `StoreTicketPreviewRequest::withValidator()` contain 82 lines of business rules.

**File**: `app/Services/TicketValidationService.php` (new)

**Methods to extract**:
- `validateCustomPrice(Product $product, float $price): void`
- `validateVoucherUniqueness(array $vouchers, int $ticketId): void`
- `validateVoucherProduct(Voucher $voucher, Product $product): void`
- `validateVoucherSeason(Voucher $voucher, Season $season): void`
- `validateVoucherUsage(Voucher $voucher, int $ticketId): void`
- `validateVoucherMembership(Voucher $voucher, Member $member): void`
- `validateTicketItems(array $items, OperatingDay $day): void` — orchestrates all above

**Controller changes**: `API/Register/TicketController::store()` calls `$validationService->validateTicketItems($items, $day)` after form request validation.

**FormRequest changes**: `StoreTicketRequest::withValidator()` reduced to basic input validation. Remove duplicate logic from `StoreTicketPreviewRequest`.

**Tests**: Add `TicketValidationServiceTest` covering each validation method.

### 2.2 Extract `MemberRegistrationValidationService`

**Problem**: `StoreMemberRegistrationRequest` hardcodes Swiss cantons.

**File**: `app/Services/MemberRegistrationValidationService.php` (new) or move cantons to `config/` or `Enum`.

**Approach**: Create `Canton` enum or move cantons to config. Replace hardcoded list in FormRequest with enum/config reference.

**Tests**: Add tests for canton validation.

---

---

## Phase 3: Synchronous everything → Queued jobs

**SKIPPED** — No queue worker available in production infrastructure.

---

## Execution order

1. Phase 2 first (validation) — smallest scope, most impactful for DRY
2. Phase 1 next (services) — larger scope, depends on validation extraction

Each phase should be a separate PR/branch.

---

## Risks

- **Phase 1**: Extracting services may require updating existing tests. Need to ensure test coverage is maintained.
- **Phase 2**: Changing FormRequest behavior may affect existing validation tests.

## Dependencies

- Phase 1 depends on existing test coverage for affected controllers.
- Phase 2 depends on existing `TicketCalculator` service (already exists).