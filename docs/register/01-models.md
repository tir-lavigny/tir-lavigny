# Register System — Models Specification

## Naming Conventions

Following Laravel conventions: snake_case table names, singular model names, clear and descriptive attributes.

| Original | Final Name | Rationale |
|---|---|---|
| Day | **OperatingDay** | Avoids collision with PHP `DateTime`/`Carbon` |
| Day.remarks | **cash_remark** | Clarifies it's about cash register reconciliation |
| Day.total_amount | **closing_balance** | Distinguishes from ticket totals |
| Season.start_amount | **initial_balance** | Cash in register at season start |
| Season.end_amount | **end_balance** | Expected cash at season end |
| TicketItem.total_price | **subtotal** | Standard term for line-item total |

## Product

| Attribute | Type | Constraints |
|---|---|---|
| `id` | bigIncrements | PK |
| `name` | string | required |
| `picture` | string | nullable (file path) |
| `unit_price` | decimal(10,2) | required, min:0 |
| `allows_custom_price` | boolean | default false |
| `category_id` | foreignId | FK → categories, cascadeOnDelete |
| `created_at` | timestamp | |
| `updated_at` | timestamp | |
| `deleted_at` | timestamp | nullable (SoftDeletes) |

### Relationships
- `belongsTo(Category::class)`
- `belongsToMany(Product::class, 'product_product', 'product_id', 'child_product_id')->withPivot('quantity')` — child products
- `belongsToMany(Product::class, 'product_product', 'child_product_id', 'product_id')->withPivot('quantity')` — parent products
- `hasMany(TicketItem::class)`
- `hasMany(Voucher::class)`

## Category

| Attribute | Type | Constraints |
|---|---|---|
| `id` | bigIncrements | PK |
| `name` | string | unique |
| `is_restricted` | boolean | default false |
| `created_at` | timestamp | |
| `updated_at` | timestamp | |
| `deleted_at` | timestamp | nullable (SoftDeletes) |

### Relationships
- `hasMany(Product::class)`

## Season

| Attribute | Type | Constraints |
|---|---|---|
| `id` | bigIncrements | PK |
| `name` | string | required |
| `start_date` | date | required |
| `end_date` | date | required, after_or_equal:start_date |
| `initial_balance` | decimal(10,2) | required |
| `end_balance` | decimal(10,2) | nullable |
| `created_at` | timestamp | |
| `updated_at` | timestamp | |
| `deleted_at` | timestamp | nullable (SoftDeletes) |

### Relationships
- `hasMany(OperatingDay::class)`
- `hasMany(Voucher::class)`

## Voucher

| Attribute | Type | Constraints |
|---|---|---|
| `id` | bigIncrements | PK |
| `code` | string | unique |
| `discount_percent` | decimal(5,2) | nullable (mutually exclusive with discount_fixed) |
| `discount_fixed` | decimal(10,2) | nullable (mutually exclusive with discount_percent) |
| `product_id` | foreignId | FK → products, cascadeOnDelete |
| `season_id` | foreignId | FK → seasons, cascadeOnDelete |
| `member_id` | foreignId | FK → members, cascadeOnDelete |
| `created_at` | timestamp | |
| `updated_at` | timestamp | |
| `deleted_at` | timestamp | nullable (SoftDeletes) |

### Notes
- Single-use: usage is tracked by presence of a `voucher_id` on a `TicketItem`
- One voucher = one product + one season + one member

### Relationships
- `belongsTo(Product::class)`
- `belongsTo(Season::class)`
- `belongsTo(Member::class)`
- `hasMany(TicketItem::class)`

### Methods
- `isUsed(): bool` — returns `$this->ticketItems()->exists()`

## Member

| Attribute | Type | Constraints |
|---|---|---|
| `id` | bigIncrements | PK |
| `first_name` | string | required |
| `last_name` | string | required |
| `created_at` | timestamp | |
| `updated_at` | timestamp | |
| `deleted_at` | timestamp | nullable (SoftDeletes) |

### Relationships
- `hasMany(Voucher::class)`

## OperatingDay

| Attribute | Type | Constraints |
|---|---|---|
| `id` | bigIncrements | PK |
| `date` | date | unique per season |
| `started_at` | datetime | when the day was opened |
| `closed_at` | datetime | nullable |
| `season_id` | foreignId | FK → seasons, cascadeOnDelete |
| `cash_remark` | text | nullable |
| `closing_balance` | decimal(10,2) | nullable |
| `total_cash` | decimal(10,2) | computed from tickets |
| `total_twint` | decimal(10,2) | computed from tickets |
| `created_at` | timestamp | |
| `updated_at` | timestamp | |

### Notes
- No soft deletes — these are immutable records
- Unique constraint: `(season_id, date)`

### Relationships
- `belongsTo(Season::class)`
- `hasMany(Ticket::class)`

### Methods
- `isOpen(): bool` — returns `$this->closed_at === null`
- `recalculateTotals(): void` — sums ticket totals by payment_method and saves

## Ticket

| Attribute | Type | Constraints |
|---|---|---|
| `id` | bigIncrements | PK |
| `total` | decimal(10,2) | |
| `payment_method` | string | enum: cash, twint |
| `operating_day_id` | foreignId | FK → operating_days, cascadeOnDelete |
| `created_at` | timestamp | |
| `updated_at` | timestamp | |
| `deleted_at` | timestamp | nullable (SoftDeletes = refunded) |

### Notes
- Soft delete = refund. Refunded tickets and their items are soft-deleted together.
- Restoring a ticket restores its items.
- Tickets are finalized at creation (no draft state)

### Relationships
- `belongsTo(OperatingDay::class)`
- `hasMany(TicketItem::class)`

## TicketItem

| Attribute | Type | Constraints |
|---|---|---|
| `id` | bigIncrements | PK |
| `product_id` | foreignId | FK → products, restrictOnDelete |
| `ticket_id` | foreignId | FK → tickets, cascadeOnDelete |
| `voucher_id` | foreignId | nullable, FK → vouchers, nullOnDelete |
| `quantity` | unsignedInteger | min:1 |
| `unit_price` | decimal(10,2) | snapshot of product price |
| `subtotal` | decimal(10,2) | computed: qty * unit_price - discount |
| `created_at` | timestamp | |
| `updated_at` | timestamp | |
| `deleted_at` | timestamp | nullable (SoftDeletes, cascaded from Ticket) |

### Relationships
- `belongsTo(Product::class)`
- `belongsTo(Ticket::class)`
- `belongsTo(Voucher::class)`

## ProductProduct (Pivot)

| Attribute | Type | Constraints |
|---|---|---|
| `product_id` | foreignId | FK → products (the bundle), cascadeOnDelete |
| `child_product_id` | foreignId | FK → products (contained item), cascadeOnDelete |
| `quantity` | unsignedInteger | default 1 |

### Notes
- Composite primary key: `(product_id, child_product_id)`
- Single-level composition only (bundles cannot contain bundles)
