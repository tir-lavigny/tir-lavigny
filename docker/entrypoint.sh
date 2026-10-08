#!/bin/sh
set -e

if [ "$(id -u)" = "0" ]; then
    if [ "${CREATE_STAGING_SQLITE_FILES:-false}" = "true" ]; then
        mkdir -p /var/www/database
        touch /var/www/database/pulse.sqlite
        touch /var/www/database/telescope.sqlite
        chown www-data:www-data /var/www/database/pulse.sqlite
        chown www-data:www-data /var/www/database/telescope.sqlite
    fi

    # storage is mounted read-only in the web container — skip there
    if [ -w storage ]; then
        chown -R www-data:www-data storage bootstrap/cache
    fi
fi

exec "$@"
