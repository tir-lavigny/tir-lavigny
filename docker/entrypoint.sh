#!/bin/sh
set -e

if [ "$(id -u)" = "0" ]; then
    if [ "${CREATE_STAGING_SQLITE_FILES:-false}" = "true" ]; then
        mkdir -p /var/www/database
        touch /var/www/database/pulse.sqlite
        touch /var/www/database/telescope.sqlite
        # directory must be www-data-writable too: sqlite creates its
        # journal/WAL files next to the database file
        chown -R www-data:www-data /var/www/database
    fi

    # storage is mounted read-only in the web container — skip there
    if [ -w storage ]; then
        chown -R www-data:www-data storage bootstrap/cache
    fi
fi

exec "$@"
