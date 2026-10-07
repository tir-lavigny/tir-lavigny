#!/bin/sh
set -e

if [ "${CREATE_STAGING_SQLITE_FILES:-false}" = "true" ]; then
    mkdir -p /var/www/database
    touch /var/www/database/pulse.sqlite
    touch /var/www/database/telescope.sqlite
    chown www-data:www-data /var/www/database/pulse.sqlite
    chown www-data:www-data /var/www/database/telescope.sqlite
fi

exec "$@"
