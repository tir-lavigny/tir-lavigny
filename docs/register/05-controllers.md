# Register System — Controllers

## Namespace Structure

```
app/Http/Controllers/
├── API/
│   └── Register/
│       ├── OperatingDayController.php    (cashier day operations)
│       ├── TicketController.php          (cashier ticket operations)
│       ├── ProductController.php         (read-only product listing)
│       ├── CategoryController.php        (read-only category listing)
│       └── VoucherValidationController.php (voucher validation)
└── BackOffice/
    └── Register/
        ├── ProductController.php         (admin CRUD)
        ├── CategoryController.php        (admin CRUD)
        ├── VoucherController.php         (admin CRUD)
        ├── SeasonController.php          (admin CRUD)
        ├── MemberController.php          (admin CRUD)
        ├── OperatingDayController.php    (admin list/show/edit)
        ├── TicketController.php          (admin refund)
        └── ReportController.php          (accountant reports)
```

---

## API Controllers (`app/Http/Controllers/API/Register/`)

### OperatingDayController

```php
class OperatingDayController extends Controller
{
    public function store(StoreDayRequest $request): JsonResponse
    public function update(UpdateDayRequest $request, OperatingDay $operatingDay): JsonResponse
    public function current(): JsonResponse
    public function show(OperatingDay $operatingDay): JsonResponse
}
```

| Method | Logic |
|---|---|
| `store()` | Validates via `StoreDayRequest` (no open day exists for season, date not duplicate). Creates `OperatingDay` with `date = today`, `started_at = now`, `season_id`. Returns the created day. |
| `update()` | Validates via `UpdateDayRequest`. Determines action from payload: `status = "closed"` → validates day is open, calls `recalculateTotals()`, sets `closed_at = now` (optionally accepts `closing_balance`); `status = "open"` → checks `reopen day` permission, sets `closed_at = null`; `cash_remark` → updates `cash_remark`. Returns updated day. |
| `current()` | Returns the currently open day for today's date, or 404. Eager-loads tickets for quick reference. |
| `show()` | Returns operating day with tickets (using `withTrashed()` for refunded tickets) and ticket items. |

### TicketController

```php
class TicketController extends Controller
{
    public function store(StoreTicketRequest $request): JsonResponse
    public function show(Ticket $ticket): JsonResponse
}
```

| Method | Logic |
|---|---|
| `store()` | Validates via `StoreTicketRequest`. Ensures operating day is open. Within a DB transaction: iterates items, validates products exist (not soft-deleted), applies voucher if provided (checks unused, season, product match — calls `VoucherValidationController` logic), computes `subtotal` (qty × unit_price — discount), sums `total`. Creates `Ticket` + `TicketItem` records. Calls `$day->recalculateTotals()`. Returns created ticket with items. |
| `show()` | Returns ticket with items, each item includes product and voucher info. Uses `withTrashed()` on items for refunded tickets. |

### ProductController

```php
class ProductController extends Controller
{
    public function index(): JsonResponse
    public function show(Product $product): JsonResponse
}
```

| Method | Logic |
|---|---|
| `index()` | Lists products where either `category.is_restricted = false` OR user has `manage products` permission. Eager-loads `category` and `childProducts`. Excludes soft-deleted products. |
| `show()` | Single product with `childProducts` and `category`. |

### CategoryController

```php
class CategoryController extends Controller
{
    public function index(): JsonResponse
}
```

| Method | Logic |
|---|---|
| `index()` | Lists non-restricted categories only (for cashier dropdowns). |

### VoucherValidationController

```php
class VoucherValidationController extends Controller
{
    public function validate(VoucherValidateRequest $request): JsonResponse
}
```

| Method | Logic |
|---|---|
| `validate()` | Accepts `code`, `product_id`, `season_id`. Validates: voucher exists, `!$voucher->isUsed()`, belongs to the given product, belongs to the given season, season is active (today between start/end). Returns discount info: `{ valid: true, type: "percent"|"fixed", value: 10.00 }` or `{ valid: false, error: "..." }`. |

---

## Back-Office Controllers (`app/Http/Controllers/BackOffice/Register/`)

These controllers follow the existing Inertia pattern (`BackOffice\EventController`, etc.). Each method returns an Inertia page or redirect.

### ProductController

```php
class ProductController extends Controller
{
    public function index(): Response          // Paginated list with search
    public function create(): Response          // Create form
    public function store(StoreProductRequest $request): RedirectResponse
    public function show(Product $product): Response  // Detail with children
    public function edit(Product $product): Response  // Edit form
    public function update(UpdateProductRequest $request, Product $product): RedirectResponse
    public function destroy(Product $product): RedirectResponse  // Soft-delete
    public function attachChild(Request $request, Product $product): RedirectResponse
    public function detachChild(Product $product, Product $child): RedirectResponse
}
```

- `attachChild()` — Validates `child_product_id` and `quantity`. Creates pivot record.
- `detachChild()` — Removes pivot record.
- Uses `SoftDeletes` scoping (only non-deleted shown by default).

### CategoryController

```php
class CategoryController extends Controller
{
    public function index(): Response
    public function create(): Response
    public function store(StoreCategoryRequest $request): RedirectResponse
    public function edit(Category $category): Response
    public function update(UpdateCategoryRequest $request, Category $category): RedirectResponse
    public function destroy(Category $category): RedirectResponse
}
```

- No `show()` — not needed for categories.

### VoucherController

```php
class VoucherController extends Controller
{
    public function index(): Response
    public function create(): Response
    public function store(StoreVoucherRequest $request): RedirectResponse
    public function edit(Voucher $voucher): Response
    public function update(UpdateVoucherRequest $request, Voucher $voucher): RedirectResponse
    public function destroy(Voucher $voucher): RedirectResponse
}
```

- No `show()` — index list is sufficient.
- Index eager-loads `product`, `season`, `member` for display.

### SeasonController

```php
class SeasonController extends Controller
{
    public function index(): Response
    public function create(): Response
    public function store(StoreSeasonRequest $request): RedirectResponse
    public function show(Season $season): Response  // With operating days summary
    public function edit(Season $season): Response
    public function update(UpdateSeasonRequest $request, Season $season): RedirectResponse
    public function destroy(Season $season): RedirectResponse
}
```

### MemberController

```php
class MemberController extends Controller
{
    public function index(): Response
    public function create(): Response
    public function store(StoreMemberRequest $request): RedirectResponse
    public function edit(Member $member): Response
    public function update(UpdateMemberRequest $request, Member $member): RedirectResponse
    public function destroy(Member $member): RedirectResponse
}
```

### OperatingDayController

```php
class OperatingDayController extends Controller
{
    public function index(): Response                        // Paginated, filterable by season
    public function show(OperatingDay $operatingDay): Response  // With tickets + refund action
    public function update(UpdateOperatingDayRequest $request, OperatingDay $operatingDay): RedirectResponse
}
```

- `update()` — Only allows editing `cash_remark` and `closing_balance`.

### TicketController (Back-Office)

```php
class TicketController extends Controller
{
    public function refund(Ticket $ticket): RedirectResponse
}
```

- `refund()` — Soft-deletes the ticket (`$ticket->delete()`). Calls `$day->recalculateTotals()`. Redirects back with success.

### ReportController

```php
class ReportController extends Controller
{
    public function dayDetail(OperatingDay $operatingDay): Response
    public function daySummary(OperatingDay $operatingDay): Response
    public function dayBalance(OperatingDay $operatingDay): Response
    public function period(Request $request): Response
    public function season(Season $season): Response
}
```

| Method | Logic |
|---|---|
| `dayDetail()` | OperatingDay with tickets (withTrashed), ticket items, products, vouchers. Shows full item-level detail. |
| `daySummary()` | OperatingDay with tickets only (no items). Shows ticket list with totals. |
| `dayBalance()` | OperatingDay totals only (total_cash, total_twint, closing_balance, cash_remark). |
| `period()` | Accepts `from`, `to`, `detail` (items\|tickets\|balance) query params. Returns list of OperatingDays in range at requested detail level. |
| `season()` | All OperatingDays in the season, ordered by date. Detail level param same as period. |
