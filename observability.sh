#!/bin/bash
set -e

echo "Checking for existing observability process ..."

if php artisan nightwatch:status; then
    echo "Existing observability process found. No action needed."
    exit 0
fi

echo "No existing observability process found. Starting new process ..."
nohup php artisan nightwatch:agent >> /dev/null 2>&1 &

sleep 5

if php artisan nightwatch:status; then
    echo "Observability process started successfully."
    exit 0
else
    echo "Failed to start observability process."
    exit 1
fi
