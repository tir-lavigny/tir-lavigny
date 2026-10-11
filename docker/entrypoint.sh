#!/bin/sh
set -e

if [ "$(id -u)" = "0" ]; then
    # storage is mounted read-only in the web container — skip there
    if [ -w storage ]; then
        chown -R www-data:www-data storage bootstrap/cache
    fi
fi

exec "$@"
