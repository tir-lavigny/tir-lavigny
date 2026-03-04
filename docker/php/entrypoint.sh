#!/bin/sh
set -e

mkdir -p storage/framework/sessions storage/framework/views storage/framework/cache storage/logs bootstrap/cache
chown -R www-data:www-data storage bootstrap/cache
chmod -R 775 storage bootstrap/cache

# Run after bind mount is available so APP_KEY and .env are accessible
php artisan package:discover --ansi

exec "$@"
