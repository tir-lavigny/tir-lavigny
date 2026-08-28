# Register System — Implementation Plan

This directory contains the specification for the register system, split into focused documents:

| File | Contents |
|---|---|
| [`01-models.md`](./01-models.md) | Model definitions, attributes, relationships |
| [`02-roles-permissions.md`](./02-roles-permissions.md) | Roles, permissions, middleware strategy |
| [`03-migrations.md`](./03-migrations.md) | Database schema, migration files |
| [`04-routes.md`](./04-routes.md) | API routes (cashier) and web routes (admin/accountant) |
| [`05-controllers.md`](./05-controllers.md) | Controller methods and business logic |
| [`06-validation.md`](./06-validation.md) | Form request validation rules |

## Architecture Overview

- **Cashier** uses a subdomain SPA consuming the Laravel API (`/api/register/*`)
- **Admin** manages data via the existing Inertia back-office (`/back-office/register/*`)
- **Accountant** views reports via the back-office (`/back-office/register/reports/*`)
- All roles use Spatie `laravel-permission` for authorization
- Sanctum token auth for API, session auth for back-office
