# Tir-Lavigny

Public site, back-office and register API for the Tir Lavigny shooting club.

Laravel + Inertia (Vue 3 + TypeScript) application.

## Stack

PHP 8.4 · Laravel 13 · Inertia (Vue 3 + TS) · MariaDB (production) / SQLite (local) · Sanctum (register API) · Spatie permission (RBAC) · Docker (multi-stage build)

## Local development

```bash
make setup-dev   # dependencies + .env + key + migrations
make seed        # sample data (needs APP_USER_* in .env)
pnpm run dev     # Vite HMR
php artisan serve
```

### Docker dev (only Docker Desktop required)

```bash
make docker-dev-setup   # first time: build, key, migrate, seed
make docker-dev-up      # http://localhost:8000 — Vite 5173, Mailpit 8025
make docker-dev-artisan CMD="migrate"
```

## Tests

- **Frontend** (Vue components): `pnpm test` — Vitest + Vue Test Utils; tests live next to the code as `*.test.ts`
- **Backend**: `php artisan test` — PHPUnit Unit + Feature suites, SQLite in-memory, `RefreshDatabase`
- **E2E**: `pnpm run test:e2e` — Playwright against a real stack (the config boots a fresh SQLite DB, migrations, seeder and frontend build)

## Code style

- `pnpm run lint` (ESLint, auto-fix) and `pnpm run format` (Prettier, `resources/` + `e2e/`)
- Backend: Pint (style) and PHPStan (static analysis)

## Health endpoints

`/up` (minimal, 204) and `/health` (DB + storage checks, JSON) — defined in `routes/health.php`, also used by container healthchecks.
