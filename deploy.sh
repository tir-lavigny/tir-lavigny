#!/bin/sh
set -e

echo "Deploying application ..."

# Enter maintenance mode
(php-8.4 artisan down --secret="bypass") || true

    # Stop Nightwatch agent if running as a nohup process
    if pgrep -f "php-8.4 artisan nightwatch:agent" > /dev/null; then
        echo "Stopping Nightwatch agent..."
        pkill -f "php-8.4 artisan nightwatch:agent"
    fi

    # Update codebase
    git fetch origin deploy
    git reset --hard origin/deploy

    # Install dependencies based on lock file
    composer_php8.4 install --no-interaction --prefer-dist --optimize-autoloader

    # Migrate database
    php-8.4 artisan migrate --force

    # Clear cache
    php-8.4 artisan optimize:clear


# Exit maintenance mode
php-8.4 artisan up

nohup php-8.4 artisan nightwatch:agent &

echo "Application deployed!"
