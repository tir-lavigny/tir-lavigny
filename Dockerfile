ARG PHP_VERSION=8.3
ARG NODE_VERSION=22

# Stage 1 - Composer deps: needed by Vite (ziggy-js aliased to vendor/tightenco/ziggy)
# Uses a pinned PHP version - composer:2 bundles latest PHP which may break the lockfile
FROM php:${PHP_VERSION}-alpine AS composer-deps

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /app
COPY composer.json composer.lock ./
RUN composer install --no-dev --no-scripts --no-autoloader --no-interaction --prefer-dist

# Stage 2 - Node: compile Vite assets (used only by the prod stage)
FROM node:${NODE_VERSION}-alpine AS node-builder

WORKDIR /app

COPY package.json package-lock.json ./
RUN npm ci

COPY . .
COPY --from=composer-deps /app/vendor ./vendor
RUN npm run build

# Stage 3 - PHP base: shared system dependencies for dev and prod
FROM php:${PHP_VERSION}-fpm AS php-base

RUN apt-get update && apt-get install -y --no-install-recommends \
    git \
    curl \
    unzip \
    zip \
    libzip-dev \
    libsqlite3-dev \
    libpng-dev \
    libonig-dev \
    libxml2-dev \
    libicu-dev \
    default-mysql-client \
    && docker-php-ext-configure intl \
    && docker-php-ext-install \
    pdo_sqlite \
    pdo_mysql \
    zip \
    gd \
    mbstring \
    xml \
    bcmath \
    intl \
    opcache \
    pcntl \
    && apt-get clean \
    && rm -rf /var/lib/apt/lists/*

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /var/www

# Stage 4 - dev: source code is bind-mounted at runtime, not copied here
FROM php-base AS dev

RUN mv "$PHP_INI_DIR/php.ini-development" "$PHP_INI_DIR/php.ini"
COPY docker/php/local.ini $PHP_INI_DIR/conf.d/local.ini

COPY composer.json composer.lock ./
RUN composer install --no-scripts --no-autoloader --no-interaction --prefer-dist

COPY . .

RUN composer dump-autoload --optimize --no-scripts \
    && chown -R www-data:www-data storage bootstrap/cache \
    && chmod -R 775 storage bootstrap/cache

COPY docker/php/entrypoint.sh /usr/local/bin/entrypoint.sh
RUN chmod +x /usr/local/bin/entrypoint.sh

EXPOSE 9000
ENTRYPOINT ["/usr/local/bin/entrypoint.sh"]
CMD ["php-fpm"]

# Stage 5 - prod: self-contained image with source code and compiled assets
FROM php-base AS prod

RUN mv "$PHP_INI_DIR/php.ini-production" "$PHP_INI_DIR/php.ini"
COPY docker/php/prod.ini $PHP_INI_DIR/conf.d/local.ini

COPY composer.json composer.lock ./
RUN composer install \
    --no-dev \
    --no-scripts \
    --no-autoloader \
    --no-interaction \
    --prefer-dist

COPY . .
COPY --from=node-builder /app/public/build ./public/build

RUN composer dump-autoload --optimize --no-scripts --no-dev \
    && chown -R www-data:www-data storage bootstrap/cache \
    && chmod -R 775 storage bootstrap/cache

COPY docker/php/entrypoint.prod.sh /usr/local/bin/entrypoint.sh
RUN chmod +x /usr/local/bin/entrypoint.sh

EXPOSE 9000
ENTRYPOINT ["/usr/local/bin/entrypoint.sh"]
CMD ["php-fpm"]
