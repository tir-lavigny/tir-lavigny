#!/bin/bash
set -euo pipefail

PORT=9000
SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
cd "$SCRIPT_DIR"

DB_PATH="$SCRIPT_DIR/database/e2e.sqlite"
SERVER_PID=""

cleanup() {
    echo "[e2e] Stopping server..."
    if [ -n "$SERVER_PID" ]; then
        kill "$SERVER_PID" 2>/dev/null || true
        wait "$SERVER_PID" 2>/dev/null || true
    fi
    if [ -f "$DB_PATH" ]; then
        rm -f "$DB_PATH"
    fi
    echo "[e2e] Done."
}
trap cleanup EXIT INT TERM

echo "[e2e] Using SQLite (e2e database at $DB_PATH)..."
export DB_CONNECTION=sqlite
export DB_DATABASE="$DB_PATH"

echo "[e2e] Running migrations + E2eSeeder..."
php artisan migrate:fresh
php artisan db:seed --class=E2eSeeder

echo "[e2e] Building front-end..."
if [ ! -d "$SCRIPT_DIR/node_modules" ]; then
    npm install
fi
npm run build

echo "[e2e] Starting Laravel server on port $PORT..."
php artisan serve --port="$PORT" --host=127.0.0.1 &
SERVER_PID=$!
sleep 5

echo "[e2e] Running Playwright tests..."
npx playwright test "${@:2}"

EXIT_CODE=$?
exit $EXIT_CODE
