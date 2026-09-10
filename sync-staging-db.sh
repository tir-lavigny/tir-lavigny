#!/bin/bash

echo "Syncing production data to staging ..."

set -a
source .env
set +a

if [ "$APP_ENV" != "staging" ]; then
    echo "Not staging environment, aborting!"
    exit 1
fi

if [[ "$(pwd)" != *staging.tir-lavigny.ch ]]; then
    echo "Not in staging directory, aborting!"
    exit 1
fi

php-8.4 artisan down --secret="bypass" || true

sync() {
    set -e
    mysql -h"$DB_HOST" -u"$DB_SYNC_USER" -p"$DB_SYNC_PASSWORD" -e "DROP DATABASE $DB_DATABASE; CREATE DATABASE $DB_DATABASE;"
    mysqldump -h"$DB_HOST" -u"$DB_SYNC_USER" -p"$DB_SYNC_PASSWORD" --single-transaction "$DB_SYNC_DATABASE" | mysql -h"$DB_HOST" -u"$DB_SYNC_USER" -p"$DB_SYNC_PASSWORD" "$DB_DATABASE"
    rm -f database/pulse.sqlite database/telescope.sqlite
    touch database/pulse.sqlite database/telescope.sqlite
    php-8.4 artisan migrate --force
}

if sync; then
    echo "Staging synced!"
else
    echo "Sync failed!"
fi

php-8.4 artisan up
