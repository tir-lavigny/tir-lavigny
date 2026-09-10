PROD RUN : MariaDB
DEV RUN : sqlite

Both don't support DDL transactions.
Both don't have the same internal functions (e.g., date/time functions, string functions).

Keep that in mind when writing migrations.

## 1. One Core Action per Migration File

The safest way to handle migrations in MariaDB is to keep them small and atomic.

- The Rule: Instead of modifying five different tables or adding unrelated features in a single file, split them up.
- Why it helps: If a migration file only does one thing (like adding columns to the tickets table) and it fails, you only have one specific area to clean up manually.

## 2. Check Before You Modify (Schema::has...)

Never assume the state of the database. Always check if a table or column exists before trying to create, alter, or drop it.

- When adding columns:

```php
if (!Schema::hasColumn('tickets', 'sold_at')) {
    Schema::table('tickets', function (Blueprint $table) {
            $table->dateTime('sold_at')->after('member_id');
    });
}
```

- When creating tables:

```php
if (!Schema::hasTable('tickets')) {
    Schema::create('tickets', function (Blueprint $table) { ... });
}
```

## 3. Separate Column Additions into Multiple Blueprint Blocks

If you absolutely must add multiple columns in a single migration file, do not bundle them into a single Schema::table function argument list if you want to avoid partial failures. Instead, break them into separate conditional blocks.
❌ Risky (If foo fails, sold_at is stuck):

```php
Schema::table('tickets', function (Blueprint $table) {
    $table->dateTime('sold_at');
    $table->string('foo'); // If this fails, sold_at still exists
});
```

Slots or separate blocks ensure that if you have to run php artisan migrate a second time, already-created columns are smoothly skipped:

```php
Schema::table('tickets', function (Blueprint $table) {
    if (!Schema::hasColumn('tickets', 'sold_at')) {
        $table->dateTime('sold_at');
    }
});

Schema::table('tickets', function (Blueprint $table) {
    if (!Schema::hasColumn('tickets', 'foo')) {
        $table->string('foo');
    }
});
```

## 4. Separate Data Seeders / Updates from Schema Changes

Never mix structural changes (CREATE, ALTER) with data manipulation (INSERT, UPDATE) in the same migration file.

- Why: If your schema change succeeds but your data insert query throws an error, MariaDB will commit the schema change. You will be left with a modified table, but Laravel will mark the migration as failed.
- The Fix: Create one migration for the new columns, and a second migration (or a dedicated Seeder) to populate the data.

## 5. Always Match Your down() Method Perfectly

If your up() method adds columns conditionally, your down() method should drop them conditionally. This prevents your rollbacks from crashing if the database state is slightly out of sync.

```php
public function down(): void
{
    Schema::table('tickets', function (Blueprint $table) {
        if (Schema::hasColumn('tickets', 'sold_at')) {
            $table->dropColumn('sold_at');
        }
        if (Schema::hasColumn('tickets', 'foo')) {
            $table->dropColumn('foo');
        }
    });
}
```

Are you setting up migrations for a production application with live user data, or is this for a local development project? Knowing this helps determine if you should focus on defensive coding or look into tools like Docker/PostgreSQL to get full transaction support.
