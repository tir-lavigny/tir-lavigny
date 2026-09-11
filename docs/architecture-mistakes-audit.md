# Architecture Mistakes Audit

Based on [10 Laravel Architecture Mistakes That Look Fine Until Your App Starts Growing](https://dev.to/hosseinhezami/10-laravel-architecture-mistakes-that-look-fine-until-your-app-starts-growing-ano).

Audit date: 2026-09-11.

---

## Summary

| # | Mistake | Present? | Severity |
|---|---------|----------|----------|
| 1 | Fat controllers | **YES** | High |
| 2 | God models | No | — |
| 3 | Validation in wrong layer | **YES** | High |
| 4 | N+1 queries | **YES** | Moderate |
| 5 | Synchronous everything | **YES** | High |
| 6 | Config as business logic | No | — |
| 7 | Middleware too much | No | — |
| 8 | Database schema | **YES** | Moderate |
| 9 | No third-party boundary | **YES** | Moderate |
| 10 | No event strategy | **YES** | Low-Mod |

---

## Mistake 1: Treating controllers as the application

### Verdict: PRESENT — High

Fat controllers with inline business logic, no service extraction, and inline validation.

### Affected controllers

#### `BackOffice/EventController` — worst offender

- `store()` lines 87-117 (30 lines): inline validation, model creation, datetime conversion, custom content creation.
- `update()` lines 167-229 (**62 lines**): inline validation, model update, datetime conversion, **25-line chain-override comparison loop** (lines 199-224).
- No FormRequest classes (`StoreEventRequest`, `UpdateEventRequest` do not exist).
- No service used. Zero service calls.

#### `BackOffice/Register/SeasonController` — pure domain logic in controller

- `getBasePackage()` lines 131-138: hardcoded product-to-count mapping.
- `resolveProducts()` lines 140-155: queries products by name, aborts if not found.
- `computePlan()` lines 157-193 (**36 lines**): core domain logic — computes voucher plan by comparing existing vouchers against required counts.
- `generateBaseVouchers()` lines 99-129 (**30 lines**): runs DB transaction, iterates over plan, creates Voucher records in a loop.
- No services used at all.

#### `BackOffice/DavController` — sync/import logic in controller

- `importFromDav()` lines 36-73 (**37 lines**): filters contacts, parses vCard, creates Member records in a loop.
- `syncPreview()` lines 75-120 (**45 lines**): builds diff pairs comparing DAV and member names.
- `sync()` lines 122-172 (**50 lines**): inline validation, loops that update member or DAV, vCard parsing.
- `InfomaniakDavService` injected but business logic stays in controller.

#### `API/Register/OperatingDayController`

- `update()` lines 31-63 (**32 lines**): inline status transitions, email sending (line 50).

#### `BackOffice/Register/ReportExportController`

- `balanceRows()`, `ticketRows()`, `itemRows()` — private methods with data-fetching and formatting logic.

#### `EventChainController`

- `chainRules()` lines 202-219: private validation rules method. Should be FormRequest.
- `update()` lines 93-127: changed-field detection loop is domain logic.

### Top extraction targets

1. `SeasonController` → `SeasonVoucherService`
2. `DavController` → `DavSyncService`
3. `EventController` → `StoreEventRequest`, `UpdateEventRequest` + service for chain logic
4. `EventChainController` → FormRequest for chain rules

---

## Mistake 2: Letting Eloquent models become service layers

### Verdict: NOT PRESENT — Clean

All 18 models are under 170 lines. Heaviest is `EventChain` (170 lines) with 3 relationships and 2 business methods.

No model calls external services, sends notifications, or contains excessive business logic.

Business logic is well-distributed:
- `Event::getEffectiveContent()`, `isFieldOverridden()`, `markFieldOverridden()`, `detachFromChain()` — field-level helpers.
- `OperatingDay::recalculateTotals()`, `isOpen()` — legitimate model methods.
- `Voucher::isUsed()` — simple existence check.
- `Member` and `Ticket` — `booted()` hooks for cascade deletes.

---

## Mistake 3: Validation in the wrong layer

### Verdict: PRESENT — High

#### `StoreTicketRequest::withValidator()` — 82 lines of business rules

Location: `app/Http/Requests/API/Register/StoreTicketRequest.php` lines 32-114.

Business rule validation:
- Product custom price check (lines 44-54): queries `products` table to check `allows_custom_price`.
- Voucher uniqueness per ticket (lines 57-63): in-memory duplicate check.
- Voucher-product matching (lines 76-80): validates voucher belongs to the product.
- Voucher-season matching (lines 83-88): validates voucher belongs to the operating day's season.
- Voucher usage check (lines 90-100): queries `ticket_items` to check if voucher was already used.
- Voucher membership check (lines 102-108): validates voucher belongs to the member.

#### `StoreTicketPreviewRequest::withValidator()` — exact copy-paste

Location: `app/Http/Requests/API/Register/StoreTicketPreviewRequest.php` lines 32-114.

Same 82 lines of business logic duplicated. DRY violation.

#### Minor findings

- `LoginRequest` contains rate-limiting and auth attempt logic (lines 27-63).
- `StoreMemberRegistrationRequest` hardcodes 23 Swiss cantons (lines 16-20).
- `StoreOperatingDayRequest` hardcodes business rule `before_or_equal:today` (line 28).

---

## Mistake 4: N+1 queries that hide behind convenience

### Verdict: PRESENT — Moderate

#### CRITICAL: UserResource and RoleResource

- `UserResource.php` line 21: `'roles' => RoleResource::collection($this->roles)` — no `whenLoaded()` guard.
- `RoleResource.php` line 20: `'permissions' => PermissionResource::collection($this->permissions)` — no `whenLoaded()` guard.
- `AuthController::me()` (line 50): creates `UserResource` without `->with('roles')`.
- Result: N+1 chain on every `/me` call (1 query for roles + N queries for permissions).

#### MEDIUM: Redundant double-queries

- `OperatingDayController::show()` lines 95-103: loads tickets via `->load()`, then re-fetches them.
- `TicketController::show()` lines 67-73: same double-query pattern.

#### LOW: Over-eager loading

- `ProductController::index()` loads `category` and `childProducts` but `ProductResource` doesn't use them.

#### LOW: Potential N+1 in reports

- `ReportController::daySummary()` line 49: `$operatingDay->tickets()->withTrashed()->get()` — no eager loading of ticketItems.
- `ReportController::period()` line 78: no eager loading of nested relationships.

---

## Mistake 5: Synchronous everything until it's not

### Verdict: PRESENT — High

#### Zero queued jobs

- `app/Jobs/` directory is empty. No class implements `ShouldQueue`.
- Queue infrastructure exists (`config/queue.php`, default: `database`) but is completely unused.

#### Synchronous email in controllers

| Location | Line | Call |
|----------|------|------|
| `API/Register/OperatingDayController.php` | 50 | `Mail::to(...)->send(new OperatingDayClosed(...))` |
| `BackOffice/UserController.php` | 101 | `Mail::to($user->email)->send(new UserCreated(...))` |

#### Synchronous email in listeners (blocks event chain)

| Location | Line | Call |
|----------|------|------|
| `SendCompletionEmail.php` | 20 | `Mail::to(...)->send(new MemberRegistrationCompletedMail(...))` |
| `SendCompletionEmail.php` | 29 | `Mail::to(...)->send(new MemberRegistrationError(...))` |
| `CreateDavContact.php` | 35 | `Mail::to(...)->send(new MemberRegistrationError(...))` |
| `CreateMemberAndVouchers.php` | 49 | `Mail::to(...)->send(new MemberRegistrationError(...))` |

#### Synchronous external API (Infomaniak DAV)

| Location | Line | Call |
|----------|------|------|
| `CreateDavContact.php` | 23 | `$this->dav->createContactFromMemberRegistration(...)` |
| `MemberController.php` | 48 | `$dav->updateMember($member)` |
| `MemberController.php` | 59 | `$dav->softDeleteMember($member)` |
| `TrashController.php` | 95 | `$dav->restoreMember($model)` |
| `TrashController.php` | 120 | `$dav->deleteMember($model)` |
| `DavController.php` | 19, 39, 78 | `$service->getContacts()` |
| `DavController.php` | 144 | `$service->getContact(...)` |
| `DavController.php` | 157 | `$service->updateMember($member)` |

#### Synchronous file generation

- `ReportExportController.php` line 37: `SimpleExcelWriter::streamDownload()`.
- `EventController.php` lines 66-96: iCal calendar generation.

---

## Mistake 6: Config files as business logic storage

### Verdict: NOT PRESENT — Clean

All config files are standard Laravel defaults. No business logic in config.

Minor: `config/app.php` lines 129-133 have first-user provisioning config (name/email/password) but this is not business logic.

---

## Mistake 7: Middleware that does too much

### Verdict: NOT PRESENT — Clean

Only 3 custom middleware, all minimal:
- `ForceJson`: sets `Accept` header.
- `HandleInertiaRequests`: shares Inertia props.
- `HandleAppearance`: shares appearance cookie value.

No business logic, side effects, or request state mutation.

---

## Mistake 8: Ignoring database schema as architectural decision

### Verdict: PRESENT — Moderate

#### String columns for enumerable states

| Table | Column | Issue |
|-------|--------|-------|
| `tickets` | `payment_method` | String, values `cash`/`twint` only. No DB constraint. |
| `member_registrations` | `status` | String, cast to PHP enum in model. No DB constraint. |
| `event_chains` | `recurrence_type` | String, comment says `daily, weekly, biweekly, monthly, custom`. No DB constraint. |
| `event_chains` | `interval_unit` | String, no DB constraint. |

#### Missing indexes

| Table | Column(s) | Issue |
|-------|-----------|-------|
| `activity_logs` | `model`, `model_id`, `action`, `user_id` | **NO indexes at all**. Will be catastrophic at scale. |

#### JSON columns that could be queried

| Table | Column | Issue |
|-------|--------|-------|
| `activity_logs` | `changes` | Stored as JSON, not cast to array. No index. |
| `events` | `overridden_fields`, `pending_conflict_fields` | JSON, only PHP array operations currently. Fragile if queries change. |
| `member_registrations` | `form_data` | JSON, accessed in PHP but not queried at DB level. |

#### Soft delete implications

- `OperatingDay` does NOT use SoftDeletes. `tickets.operating_day_id` uses `cascadeOnDelete`. Hard-deleting an operating day permanently deletes all its tickets (including soft-deleted ones).
- `Ticket` cascades soft deletes to `TicketItem` via `booted()` event.
- `Member` cascades soft deletes to `Voucher` (if no ticket items) via `booted()` event.

---

## Mistake 9: No boundary between code and third-party services

### Verdict: PRESENT — Moderate

#### InfomaniakDavService — direct usage, no interface

- `app/Services/InfomaniakDavService.php` (474 lines): directly creates `Sabre\DAV\Client`.
- No interface or abstraction layer. Registered as singleton in `AppServiceProvider`.
- Used directly in 3 controllers + 1 listener.
- Tests mock the concrete class (`Mockery::mock(InfomaniakDavService::class)`).

#### Email sending — no abstraction

- Direct `Mail::to()->send()` calls scattered across 6 locations (controllers + listeners).
- No dedicated mail service class.

---

## Mistake 10: No event strategy until you need one

### Verdict: PRESENT — Low-Moderate

#### Limited scope

Only 4 events and 4 listeners. Used for:
1. Member registration pipeline (3 events, 3 listeners).
2. Event chain generation (1 event, 1 listener).

#### Listeners mutate model state directly

- `CreateDavContact.php` lines 25-27: sets `$registration->dav_uid`, `$registration->status`, calls `$registration->save()`.
- `CreateMemberAndVouchers.php` lines 31-33: sets `$registration->member`, `$registration->status`, calls `$registration->save()`.
- `SendCompletionEmail.php` lines 22-23: sets `$registration->status`, calls `$registration->save()`.

#### No error recovery/retry

Failed steps set `error_message` and `failed_at` on registration. No automatic retry. Only manual `retry()` action in `MemberRegistrationController` (line 65-80).

#### Events carry minimal payload

All events carry only a model instance. No metadata, no correlation IDs, no trigger context.

#### No EventServiceProvider

Events are auto-discovered via Laravel convention. No explicit `$listen` mapping.

---

## Priority ranking for fixes

1. **Fat controllers** (Mistake 1) — extract to services, add FormRequests
2. **Validation in wrong layer** (Mistake 3) — move business rules to service/action
3. **Synchronous everything** (Mistake 5) — introduce queued jobs for email/DAV
4. **N+1 queries** (Mistake 4) — add `whenLoaded()` guards, eager loading
5. **Third-party boundary** (Mistake 9) — introduce interface for DAV service
6. **Database schema** (Mistake 8) — add indexes, consider enums for string columns
7. **Event strategy** (Mistake 10) — enrich event payloads, avoid listener state mutation