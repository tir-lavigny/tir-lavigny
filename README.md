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
