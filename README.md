[![Deploy to Main](https://github.com/zurfjereluhmie/tir-lavigny.ch/actions/workflows/main.yml/badge.svg)](https://github.com/zurfjereluhmie/tir-lavigny.ch/actions/workflows/main.yml) [![Deploy to Staging](https://github.com/zurfjereluhmie/tir-lavigny.ch/actions/workflows/staging.yml/badge.svg?branch=dev)](https://github.com/zurfjereluhmie/tir-lavigny.ch/actions/workflows/staging.yml)

# CI/CD Pipeline

## Branch model

```
feature branches → PR → dev → auto-deploy to staging
                                    ↓
                              PR → main → auto-deploy to production
```

| Branch | Environment | URL                        |
| ------ | ----------- | -------------------------- |
| `dev`  | Staging     | https://dev.tir-lavigny.ch |
| `main` | Production  | https://tir-lavigny.ch     |

## Workflows

### CI (tests + lint)

| Workflow           | Trigger                           | What it does                                   |
| ------------------ | --------------------------------- | ---------------------------------------------- |
| `tests.yml`        | PR to `dev`, `workflow_call`      | PHP + JS tests, type check                     |
| `lint.yml`         | PR to `dev`, `workflow_call`      | Pint, Prettier, ESLint (auto-commits fixes)    |
| `docker-build.yml` | Push to `dev`, PR to `main`/`dev` | Smoke test: builds Docker prod image (no push) |

### Staging deploy

**Trigger:** Push to `dev` or manual dispatch.

**Flow:**

```
staging.yml triggers
  ├─ tests (reusable workflow)
  ├─ lint (reusable workflow) → auto-commits formatting fixes
  └─ build + deploy (needs: [tests, lint])
       → checks out latest dev HEAD (includes lint fixes)
       → builds Vite assets → pushes to staging-deploy branch
       → SSH into VPS → runs deploy-staging.sh
       → cleans up staging-deploy branch
```

**Concurrency:** Only one staging deploy at a time (`staging-deploy` group).

**Deploy script** (`deploy-staging.sh`):

1. Enters maintenance mode
2. Fetches `staging-deploy` branch, resets to it
3. Installs Composer dependencies
4. Runs `artisan migrate --force`
5. Clears cache
6. Exits maintenance mode

On failure: rolls back code to previous commit, reinstalls deps, app comes back up on old version.

### Production deploy

**Trigger:** Push to `main` or manual dispatch.

**Flow:** Same as staging but deploys to production directory and uses `deploy.sh`.

### Staging DB sync

**Trigger:** Manual dispatch only (`staging-sync.yml`).

**Flow:** SSHs to VPS, runs `sync-staging-db.sh`:

1. Drops staging DB, recreates empty
2. Dumps prod DB → restores to staging DB (with `--single-transaction`)
3. Recreates Pulse + Telescope SQLite files
4. Runs `artisan migrate --force` (catches up dev-only migrations)

## Required secrets

| Secret                | Purpose                  |
| --------------------- | ------------------------ |
| `MASTER_SSH_USERNAME` | VPS SSH user             |
| `MASTER_SSH_HOST`     | VPS hostname/IP          |
| `MASTER_SSH_KEY`      | VPS SSH private key      |
| `MASTER_SSH_PORT`     | VPS SSH port             |
| `MASTER_SSH_PATH`     | Prod directory on VPS    |
| `STAGING_SSH_PATH`    | Staging directory on VPS |

## VPS prerequisites

### Staging

- Directory at `STAGING_SSH_PATH` with correct permissions
- `.env` file with `APP_ENV=staging`, `APP_URL=https://dev.tir-lavigny.ch`
- `deploy-staging.sh` and `sync-staging-db.sh` present and executable
- MySQL user with read access to prod DB + full access to staging DB
- Apache vhost for `dev.tir-lavigny.ch`

### Production

- Directory at `MASTER_SSH_PATH` with correct permissions
- `.env` file with `APP_ENV=production`
- `deploy.sh` present and executable
- MySQL database for production

---

# Tests

## Frontend

Vue 3 + TypeScript component tests via **Vitest** + **Vue Test Utils**.

**Run:**

```bash
npm test
```

**Config:** `vitest.config.ts`

- Environment: `jsdom`
- Test pattern: `resources/js/**/*.test.ts`
- Setup: `resources/js/tests/setup.ts` (mocks Ziggy routes, Leaflet maps, Inertia)

**Tests cover:** Vue components, composables, and any frontend logic. Unit-level, no HTTP calls, no real browser.

**Add a test:** create `resources/js/something.test.ts` alongside the code. Vitest picks it up automatically.

## Backend

PHP tests via **PHPUnit**, split into Unit and Feature suites.

**Run:**

```bash
./vendor/bin/phpunit
```

**Config:** `phpunit.xml`

- Test env: `APP_ENV=testing`, `DB_CONNECTION=sqlite`, `DB_DATABASE=:memory:`
- `RefreshDatabase`: isolated state per test
- Mail, cache, queue, session all faked/array-backed

| Suite   | Directory        | Scope                                      |
| ------- | ---------------- | ------------------------------------------ |
| Unit    | `tests/Unit/`    | Isolated logic (services, traits, helpers) |
| Feature | `tests/Feature/` | HTTP routes, controllers, auth flows       |

**Examples:**

- `ConvertsLocalDatetimesTest.php`: UTC conversion logic
- `DashboardTest.php`: authenticated route access
- `MemberRegistrationFormTest.php`: form validation & submission

**Add a test:** create a file in `tests/Unit/` or `tests/Feature/` extending `Tests\TestCase`. Database state resets automatically.

---

# E2E

End-to-end tests use Playwright against the Laravel + Vue app.

**Run:**

```bash
npm run test:e2e
```

**How it works:**

1. `playwright.config.ts` starts `run-e2e-server.sh` via `webServer` before tests
2. The script creates a fresh SQLite DB (`database/e2e.sqlite`), runs migrations, and seeds with `E2eSeeder`
3. `E2eSeeder` creates events using **relative times** (e.g. `now()->subDays(5)`, `now()->addDays(14)`) converted from Europe/Zurich local to UTC, tests never expire
4. Frontend builds if `public/build` is missing
5. Laravel server starts on `http://localhost:9000`
6. Playwright runs all specs in `e2e/` and kills the server

**Files:**

| File                             | Role                                         |
| -------------------------------- | -------------------------------------------- |
| `playwright.config.ts`           | Browser config, webServer command, port 9000 |
| `run-e2e-server.sh`              | DB setup, build, server startup              |
| `database/seeders/E2eSeeder.php` | Relative-time test data                      |
| `e2e/`                           | Test specs                                   |

**Add a test:** create `e2e/my-test.spec.ts` with `import { test, expect } from '@playwright/test'`. It runs automatically with `npm run test:e2e`.

---

# Setup dev environment

## Makefile

```
make setup-dev
```

## .env

```
APP_USER_EMAIL=
APP_USER_NAME=
APP_USER_PASSWORD=
```

## Seed the database with sample data

```
make seed
```

## Run the application

```
npm run dev
php artisan serve
```

---

# Docker - Development

Requires only Docker Desktop. No PHP, Composer or Node needed locally.

**First-time setup:**

```bash
make docker-dev-setup
```

This single command creates the SQLite files, copies `.env.example`, builds the image, generates the app key and runs migrations with seeders.

| Service  | URL                   |
| -------- | --------------------- |
| App      | http://localhost:8000 |
| Vite HMR | http://localhost:5173 |
| Mailpit  | http://localhost:8025 |

**Daily usage:**

```bash
make docker-dev-up       # Start the stack
make docker-dev-down     # Stop the stack
make docker-dev-logs     # Follow app logs
make docker-dev-shell    # Shell inside the app container
make docker-dev-artisan CMD="migrate"  # Run any Artisan command
```

---

# Docker - Production

> **Prerequisites:** A VPS/Cloud Server with Docker installed, ports 80 and 443 open, and the DNS `A` record for `tir-lavigny.ch` already pointing to the server.
> This will **not** work on shared/mutualized hosting (e.g. Infomaniak Web Hosting).

## 1. Clone the repository on the server

```bash
git clone https://github.com/zurfjereluhmie/tir-lavigny.ch.git
cd tir-lavigny.ch
```

## 2. Create the production environment file

```bash
cp .env.prod.example .env.prod
```

Edit `.env.prod` and fill in all required values:

| Variable           | Description                                                                                                  |
| ------------------ | ------------------------------------------------------------------------------------------------------------ |
| `APP_KEY`          | Generate with `docker run --rm php:8.3-cli php -r "echo 'base64:'.base64_encode(random_bytes(32)).PHP_EOL;"` |
| `DB_PASSWORD`      | Strong password for the MariaDB app user                                                                     |
| `DB_ROOT_PASSWORD` | Strong password for the MariaDB root user                                                                    |
| `MAIL_HOST`        | SMTP host (e.g. `smtp.infomaniak.com`)                                                                       |
| `MAIL_USERNAME`    | SMTP username                                                                                                |
| `MAIL_PASSWORD`    | SMTP password                                                                                                |

## 3. First-time setup (SSL + start)

```bash
make docker-prod-setup EMAIL=your@email.com
```

This command:

1. Checks that `.env.prod` exists
2. Runs `init-ssl.sh` to obtain a Let's Encrypt certificate
3. Starts the full stack (app, worker, nginx, certbot, MariaDB)

The `entrypoint.prod.sh` script automatically caches config/routes/views and runs `php artisan migrate --force` on every container start.

## 4. Subsequent deployments

```bash
make docker-prod-deploy
```

Pulls the latest code, rebuilds the image (including fresh Vite assets) and restarts the stack with zero-downtime container replacement.

**Other useful commands:**

```bash
make docker-prod-up      # Start the stack (no rebuild)
make docker-prod-down    # Stop the stack
make docker-prod-logs    # Follow app logs
make docker-prod-shell   # Shell inside the app container
make docker-prod-artisan CMD="migrate:status"  # Run any Artisan command
make docker-prod-ssl EMAIL=your@email.com      # Re-issue the SSL certificate
```

| Service | URL                    |
| ------- | ---------------------- |
| App     | https://tir-lavigny.ch |
| MariaDB | internal (port 3306)   |
