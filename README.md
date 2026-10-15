[![CI](https://github.com/zurfjereluhmie/tir-lavigny.ch/actions/workflows/ci.yml/badge.svg)](https://github.com/zurfjereluhmie/tir-lavigny.ch/actions/workflows/ci.yml)

# Tir-Lavigny — Laravel + Inertia (Vue 3) application

Public site, back-office and register API for Tir Lavigny. Runs as immutable Docker images (GHCR) on the Tir-Lavigny VPS, managed by the [tir-lavigny-infra](https://github.com/tir-lavigny/tir-lavigny-infra) repository (Ansible + Docker Compose). The infrastructure architecture, TLS, backups, monitoring and cutover plan live in that repo's `PLAN.md` — this README covers only the application.

---

# CI/CD

Everything is **tag-based** (single branch: `main`). There is no legacy-VPS deploy path in this repo anymore.

## Branch model

```
push to main
  → CI (tests + lint, check-only)
  → green CI auto-creates vX.Y.Z-dev.N tag
  → image build (app + web, SHA + version tags)
  → staging auto-deploys via SSH

manual release
  → vX.Y.Z tag + GitHub release
  → image build only (lands in GHCR)

manual prod deploy
  → workflow_dispatch with the vX.Y.Z tag
  → prod deploy via SSH
```

| Environment | URL                          | Trigger                                    |
| ----------- | ---------------------------- | ------------------------------------------ |
| Staging     | https://staging.tir-lavigny.ch | Auto: green CI on `main` → dev tag → deploy |
| Production  | https://tir-lavigny.ch       | Manual: `deploy-production.yml` dispatch    |

## Workflows

| Workflow               | Trigger                              | What it does                                                                          |
| ---------------------- | ------------------------------------ | ------------------------------------------------------------------------------------- |
| `ci.yml`               | Push to `main`, PRs                  | Calls `tests.yml` + `lint.yml`                                                         |
| `tests.yml`            | `workflow_call`                      | PHPUnit + Vitest                                                                       |
| `lint.yml`             | `workflow_call`                      | Check-only: Pint `--test`, PHPStan, Prettier `--check`, ESLint — **CI never commits**  |
| `dev-tag.yml`          | Green CI on `main`                   | Auto-creates `vX.Y.Z-dev.N` tag (GitHub App token, so the tag triggers `docker.yml`)   |
| `docker.yml`           | `v*` tags + `workflow_dispatch`      | Builds app + web images → GHCR; `-dev` tags then auto-deploy staging                   |
| `release.yml`          | `workflow_dispatch` (version input)  | Creates `vX.Y.Z` tag + GitHub release                                                  |
| `deploy-production.yml`| `workflow_dispatch` (tag input)      | Deploys an existing GHCR release tag to production                                     |
| `staging-sync.yml`     | `workflow_dispatch`                  | Copies the production DB into staging (see below)                                      |

Safety properties:

- **Staging** is CI-gated: no green CI → no dev tag → no image → no deploy.
- **Production** is a deliberate human action: the dispatch click is the approval (environment reviewer gates need a GitHub Team plan).
- Every deploy runs a **pre-migration DB dump** and a **health gate** (`/up` must answer); a failed gate or migration triggers the **rollback trap** — previous tag is restored automatically.
- The register PWA is a separate repo with its own release cycle (`--register` deploys never restart this app, and vice versa — API changes must stay backward-compatible for at least one deploy cycle).

## Deploying

### Staging

Nothing to do — push to `main`, watch the CI → dev tag → deploy chain. Deploy scripts live on the VPS (`/usr/local/sbin/tir-lavigny-deploy-staging`, Ansible-installed) and run migrations + `db:seed` (idempotent RBAC seeder) + `artisan optimize` + health check.

### Production

1. **Release**: Actions → `Release` → input version (e.g. `0.2.0`) → creates `v0.2.0` + build.
2. **Wait** for `docker.yml` to finish (image in GHCR).
3. **Deploy**: Actions → `Deploy to production` → input `v0.2.0`.

Manual fallback (SSH to the VPS): `sudo tir-lavigny-deploy-production v0.2.0`.

### Staging DB sync

Actions → `staging-sync` → dispatch. SSHes into the VPS and runs `tir-lavigny-sync-staging`: dumps the prod DB, drops + recreates the staging schema, imports, runs `migrate` + idempotent seed. Aborts if prod/staging DB names ever match.

## Required secrets (this repo)

| Secret                  | Purpose                                                     |
| ----------------------- | ----------------------------------------------------------- |
| `VPS_SSH_HOST`          | VPS hostname for deploy SSH                                  |
| `VPS_SSH_USER`          | Deploy user on the VPS                                       |
| `VPS_SSH_PRIVATE_KEY`   | Private key for that user (CI key, also used by qr + register repos) |
| `APP_CLIENT_ID`         | GitHub App id used to mint the tag-push token (`dev-tag.yml`) |
| `APP_PRIVATE_KEY`       | GitHub App private key                                       |

Everything else (DB creds, mail, Nightwatch token, …) lives in **Doppler** and reaches the VPS via `tir-lavigny-refresh-env` (Ansible-instained), never in GitHub.

## Observability

- **Nightwatch** (nightwatch.laravel.com, EU): app-level APM — requests, exceptions, queries. `NIGHTWATCH_ENABLED=false` in the test suite.
- **Telescope** (back-office, admin only): per-request inspection. DB lives in its own MariaDB schema (`0g9zd_telescope`). `telescope:prune` runs weekly via the scheduler.
- Health endpoints: `/up` (minimal, 204) and `/health` (DB + storage checks, JSON) — both grouped in `routes/health.php`, excluded from Nightwatch sampling, used by deploy health gates and the compose healthchecks.

---

# Tests

## Frontend

Vue 3 + TypeScript component tests via **Vitest** + **Vue Test Utils**.

```bash
pnpm test
```

Config: `vitest.config.ts` — jsdom, `resources/js/**/*.test.ts`, setup mocks Ziggy/Leaflet/Inertia. Add a test next to the code as `something.test.ts`; it is picked up automatically.

## Backend

PHP tests via **PHPUnit**, Unit + Feature suites.

```bash
php artisan test
```

Test env: `APP_ENV=testing`, sqlite `:memory:`, `RefreshDatabase` per test, mail/cache/queue faked. Add tests in `tests/Unit/` or `tests/Feature/`.

## E2E

Playwright against a real Laravel + Vue stack:

```bash
pnpm run test:e2e
```

`playwright.config.ts` starts `run-e2e-server.sh`: fresh SQLite DB (`database/e2e.sqlite`), migrations, `E2eSeeder` (relative-time data so tests never expire), frontend build if needed, server on `http://localhost:9000`. Add specs in `e2e/*.spec.ts`.

---

# Development

## Local setup (no Docker)

```bash
make setup-dev   # dependencies + .env + key + migrations
make seed        # sample data (needs APP_USER_* in .env)
pnpm run dev     # Vite HMR
php artisan serve
```

## Docker dev (only Docker Desktop required)

```bash
make docker-dev-setup   # first time: build, key, migrate, seed
make docker-dev-up      # http://localhost:8000 — Vite 5173, Mailpit 8025
make docker-dev-artisan CMD="migrate"
```

## Health routes & scheduled tasks

- `routes/health.php` — `/up`, `/health`, `/api/health` (register PWA offline check)
- `routes/console.php` — `telescope:prune` (weekly), `idempotency:prune` (daily)

## Makefile note

Only dev targets exist (`docker-dev-*`, local `docker-compose.yml`). Production deploys are CI-driven GHCR image swaps via the infra repo — there is no in-repo prod path anymore (the legacy shared-hosting scripts were deleted 2026-10-15).

---

# Stack

PHP 8.4 · Laravel 13 · Inertia (Vue 3 + TS) · MariaDB · Redis-less (database cache/sessions) · Sanctum (register API) · Spatie permission (RBAC) · Nightwatch · Telescope · Docker (multi-stage: composer deps → node build → php-fpm + nginx web image)
