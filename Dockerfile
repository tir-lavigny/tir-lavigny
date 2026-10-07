ARG PHP_VERSION=8.4
ARG NODE_VERSION=24

# ============================================================
# PHP base with all runtime extensions
# ============================================================

FROM php:${PHP_VERSION}-fpm-bookworm AS php-base

WORKDIR /var/www

RUN apt-get update \
    && apt-get install -y --no-install-recommends \
    libicu-dev \
    libonig-dev \
    libpng-dev \
    libsqlite3-dev \
    libxml2-dev \
    libzip-dev \
    unzip \
    zip \
    && docker-php-ext-configure intl \
    && docker-php-ext-install -j"$(nproc)" \
    bcmath \
    gd \
    intl \
    mbstring \
    opcache \
    pdo_mysql \
    pdo_sqlite \
    xml \
    zip \
    && rm -rf /var/lib/apt/lists/*


# ============================================================
# Composer dependencies
# ============================================================

FROM php-base AS composer-deps

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

COPY composer.json composer.lock ./

RUN composer install \
    --no-dev \
    --no-interaction \
    --prefer-dist \
    --no-scripts \
    --no-autoloader

COPY . .

RUN composer dump-autoload \
    --no-dev \
    --optimize \
    --classmap-authoritative \
    --no-scripts


# ============================================================
# Frontend build
# ============================================================

FROM node:${NODE_VERSION}-bookworm-slim AS frontend

WORKDIR /app

COPY package.json pnpm-lock.yaml pnpm-workspace.yaml ./

RUN corepack enable \
    && pnpm install --frozen-lockfile

COPY . .

COPY --from=composer-deps /var/www/vendor ./vendor

RUN pnpm run build


# ============================================================
# Laravel PHP-FPM production image
# ============================================================

FROM php-base AS production

COPY docker/php/prod.ini \
    "$PHP_INI_DIR/conf.d/99-production.ini"

COPY . .

COPY --from=composer-deps \
    /var/www/vendor \
    ./vendor

COPY --from=frontend \
    /app/public/build \
    ./public/build

RUN mkdir -p \
    storage/framework/cache \
    storage/framework/sessions \
    storage/framework/views \
    storage/logs \
    bootstrap/cache \
    && chown -R www-data:www-data \
    storage \
    bootstrap/cache \
    && chmod -R ug+rwX \
    storage \
    bootstrap/cache

EXPOSE 9000

CMD ["php-fpm"]


# ============================================================
# Laravel Nginx image
# ============================================================

FROM nginx:1.28-alpine AS web

COPY docker/nginx/default.conf \
    /etc/nginx/conf.d/default.conf

COPY --from=frontend \
    /app/public \
    /var/www/public

EXPOSE 80
