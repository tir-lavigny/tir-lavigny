ARG PHP_VERSION=8.4
ARG NODE_VERSION=24

ARG VCS_URL=https://github.com/tir-lavigny/tir-lavigny-laravel

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


FROM node:${NODE_VERSION}-bookworm-slim AS frontend

WORKDIR /app

COPY package.json pnpm-lock.yaml pnpm-workspace.yaml ./

RUN corepack enable \
    && pnpm install --frozen-lockfile

COPY . .

COPY --from=composer-deps /var/www/vendor ./vendor

RUN pnpm run build


FROM php-base AS production

LABEL org.opencontainers.image.source="https://github.com/tir-lavigny/tir-lavigny"

COPY docker/php/prod.ini \
    "$PHP_INI_DIR/conf.d/99-production.ini"

COPY . .
COPY docker/entrypoint.sh /usr/local/bin/entrypoint.sh
RUN chmod +x /usr/local/bin/entrypoint.sh

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

ENTRYPOINT ["/usr/local/bin/entrypoint.sh"]

CMD ["php-fpm"]


FROM nginx:1.28-alpine AS web

LABEL org.opencontainers.image.source="https://github.com/tir-lavigny/tir-lavigny"

COPY docker/nginx/default.conf \
    /etc/nginx/conf.d/default.conf

COPY --from=frontend \
    /app/public \
    /var/www/public

EXPOSE 80
