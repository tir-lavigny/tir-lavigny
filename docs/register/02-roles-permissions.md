# Register System — Roles & Permissions

## Overview

Only the cashier uses the API. Admin and accountant operate exclusively via the admin panel.

| Who | Where | Auth |
|---|---|---|
| **Cashier** | API (`/api/register/*`) | Sanctum `auth:sanctum` + `role:cashier\|admin` |
| **Admin** | Back-office (`/back-office/register/*`) | Web `auth` + `verified` + Spatie `permission` |
| **Accountant** | Back-office (`/back-office/register/reports/*`) | Web `auth` + `verified` + `permission:view reports` |

## Roles

### New Roles
- **cashier** — Operates the register via the API subdomain
- **accountant** — Views reports via the admin panel

### Existing Roles Modified
- **admin** — Gets all new register permissions (via seeder)
- **editor** — Unchanged
- **user** — Unchanged

## Permissions (18 new)

| Permission | cashier | accountant | admin |
|---|---|---|---|
| `view register` | ✓ | ✓ | ✓ |
| `open day` | ✓ | | ✓ |
| `close day` | ✓ | | ✓ |
| `reopen day` | | | ✓ |
| `add cash remark` | ✓ | | ✓ |
| `create ticket` | ✓ | | ✓ |
| `view tickets` | ✓ | ✓ | ✓ |
| `refund ticket` | | | ✓ |
| `add product to ticket` | ✓ | | ✓ |
| `apply voucher to ticket` | ✓ | | ✓ |
| `manage products` | | | ✓ |
| `manage categories` | | | ✓ |
| `manage vouchers` | | | ✓ |
| `manage seasons` | | | ✓ |
| `manage members` | | | ✓ |
| `manage operating days` | | | ✓ |
| `view reports` | | ✓ | ✓ |
| `manage register settings` | | | ✓ |

## Seeder Plan

The `RolesAndPermissionsSeeder` will be updated to:
1. Create all 18 new permissions
2. Assign all 18 to `admin` role
3. Assign cashier-relevant permissions to `cashier` role
4. Assign report permissions to `accountant` role
5. Create the new `cashier` and `accountant` roles

## Middleware Usage

### API Routes
```php
Route::middleware(['auth:sanctum', 'role:cashier|admin'])->prefix('register')->group(function () { ... });
```

### Back-Office Routes
```php
Route::middleware('permission:manage products')->group(function () { ... });
Route::middleware('permission:view reports')->group(function () { ... });
```
