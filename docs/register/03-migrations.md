# Register System — Database Schema (Migrations)

> Run order is important. Each section corresponds to one migration file.

## Migration 1: Create Members Table

```php
Schema::create('members', function (Blueprint $table) {
    $table->id();
    $table->string('first_name');
    $table->string('last_name');
    $table->string('member_number')->unique();
    $table->softDeletes();
    $table->timestamps();
});
```

## Migration 2: Create Categories Table

```php
Schema::create('categories', function (Blueprint $table) {
    $table->id();
    $table->string('name')->unique();
    $table->boolean('is_restricted')->default(false);
    $table->softDeletes();
    $table->timestamps();
});
```

## Migration 3: Create Products Table

```php
Schema::create('products', function (Blueprint $table) {
    $table->id();
    $table->string('name');
    $table->string('picture')->nullable();
    $table->decimal('unit_price', 10, 2);
    $table->boolean('allows_custom_price')->default(false);
    $table->foreignId('category_id')->constrained()->cascadeOnDelete();
    $table->softDeletes();
    $table->timestamps();
});
```

## Migration 4: Create Product-Product Pivot Table

```php
Schema::create('product_product', function (Blueprint $table) {
    $table->foreignId('product_id')->constrained()->cascadeOnDelete();
    $table->foreignId('child_product_id')->constrained('products')->cascadeOnDelete();
    $table->unsignedInteger('quantity')->default(1);
    $table->primary(['product_id', 'child_product_id']);
});
```

## Migration 5: Create Seasons Table

```php
Schema::create('seasons', function (Blueprint $table) {
    $table->id();
    $table->string('name');
    $table->date('start_date');
    $table->date('end_date');
    $table->decimal('initial_balance', 10, 2)->default(0);
    $table->decimal('expected_end_balance', 10, 2)->nullable();
    $table->softDeletes();
    $table->timestamps();
});
```

## Migration 6: Create Vouchers Table

```php
Schema::create('vouchers', function (Blueprint $table) {
    $table->id();
    $table->string('code')->unique();
    $table->decimal('discount_percent', 5, 2)->nullable();
    $table->decimal('discount_fixed', 10, 2)->nullable();
    $table->foreignId('product_id')->constrained()->cascadeOnDelete();
    $table->foreignId('season_id')->constrained()->cascadeOnDelete();
    $table->foreignId('member_id')->constrained()->cascadeOnDelete();
    $table->softDeletes();
    $table->timestamps();

    $table->index('season_id');
    $table->index('product_id');
});
```

## Migration 7: Create Operating Days Table

```php
Schema::create('operating_days', function (Blueprint $table) {
    $table->id();
    $table->date('date');
    $table->dateTime('started_at');
    $table->dateTime('closed_at')->nullable();
    $table->foreignId('season_id')->constrained()->cascadeOnDelete();
    $table->text('cash_remark')->nullable();
    $table->decimal('closing_balance', 10, 2)->nullable();
    $table->decimal('total_cash', 10, 2)->default(0);
    $table->decimal('total_twint', 10, 2)->default(0);
    $table->timestamps();

    $table->unique(['season_id', 'date']);
    $table->index('season_id');
});
```

## Migration 8: Create Tickets Table

```php
Schema::create('tickets', function (Blueprint $table) {
    $table->id();
    $table->decimal('total', 10, 2);
    $table->string('payment_method');
    $table->foreignId('operating_day_id')->constrained()->cascadeOnDelete();
    $table->softDeletes();
    $table->timestamps();

    $table->index('operating_day_id');
    $table->index('payment_method');
});
```

## Migration 9: Create Ticket Items Table

```php
Schema::create('ticket_items', function (Blueprint $table) {
    $table->id();
    $table->foreignId('product_id')->constrained()->restrictOnDelete();
    $table->foreignId('ticket_id')->constrained()->cascadeOnDelete();
    $table->foreignId('voucher_id')->nullable()->constrained()->nullOnDelete();
    $table->unsignedInteger('quantity');
    $table->decimal('unit_price', 10, 2);
    $table->decimal('subtotal', 10, 2);
    $table->timestamps();

    $table->index('ticket_id');
    $table->index('voucher_id');
});
```

## Migration 10: Seed Register Permissions & Roles

This migration updates the existing `RolesAndPermissionsSeeder`:
1. Create all 18 new permissions (see [02-roles-permissions.md](./02-roles-permissions.md))
2. Assign all to `admin` role
3. Create `cashier` role with relevant permissions
4. Create `accountant` role with `view reports` and `view tickets`
