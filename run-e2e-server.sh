#!/bin/bash
set -euo pipefail

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
cd "$SCRIPT_DIR"

DB_PATH="$SCRIPT_DIR/database/e2e.sqlite"

export DB_CONNECTION=sqlite
export DB_DATABASE="$DB_PATH"

echo "[e2e] Using SQLite (e2e database at $DB_PATH)..."
echo "[e2e] Running migrations + E2eSeeder..."
php artisan migrate:fresh
php artisan db:seed --class=E2eSeeder

echo "[e2e] Building front-end..."
if [ ! -d "$SCRIPT_DIR/node_modules" ]; then
    pnpm install
fi
pnpm run build

echo "[e2e] Starting Laravel server on port 9000..."
php artisan serve --port=9000 --host=127.0.0.1
