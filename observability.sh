#!/bin/bash
set -e

cd "$(dirname "$0")"

PID_FILE="observability.pid"

echo "Checking for existing observability process ..."

if php artisan nightwatch:status; then
    echo "Existing observability process found."
    if [ -f "$PID_FILE" ]; then
        PID=$(cat "$PID_FILE")
        if [ -n "$PID" ]; then
            # Attempt beautiful/graceful shutdown
            kill -15 "$PID" 2>/dev/null || true
            sleep 2
            # Force kill only if still active
            kill -9 "$PID" 2>/dev/null || true
        fi
        rm -f "$PID_FILE"
    fi
else
    echo "No existing observability process found."
fi

echo "Starting new process ..."
nohup php artisan nightwatch:agent > /dev/null 2>&1 &
echo $! > "$PID_FILE"

sleep 5

if php artisan nightwatch:status; then
    echo "Observability process started successfully."
    exit 0
else
    echo "Failed to start observability process."
    exit 1
fi
