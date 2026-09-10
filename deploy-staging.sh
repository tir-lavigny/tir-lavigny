#!/bin/bash

echo "Deploying staging application ..."

PREVIOUS_COMMIT=$(git rev-parse HEAD)

php-8.4 artisan down --secret="bypass" || true

deploy() {
    set -e
    git fetch origin staging-deploy
    git reset --hard origin/staging-deploy
    composer_php8.4 install --no-interaction --prefer-dist --optimize-autoloader
    php-8.4 artisan migrate --force
    php-8.4 artisan optimize:clear
}

if deploy; then
    echo "Staging application deployed!"
else
    echo "Deploy failed! Rolling back..."
    git reset --hard "$PREVIOUS_COMMIT"
    composer_php8.4 install --no-interaction --prefer-dist --optimize-autoloader
fi

php-8.4 artisan up
