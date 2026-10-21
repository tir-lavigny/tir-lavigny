ARG PHP_VERSION=8.4.27
ARG NODE_VERSION=24.22.0

ARG VCS_URL=https://github.com/tir-lavigny/tir-lavigny-laravel

FROM php:${PHP_VERSION}-fpm-alpine AS php-base

WORKDIR /var/www

RUN apk add --no-cache \
    icu-data-full \
    icu-dev \
    libpng-dev \
    libxml2-dev \
    libzip-dev \
    oniguruma-dev \
    sqlite-dev \
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
    zip


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


FROM node:${NODE_VERSION}-alpine AS frontend

ARG VITE_APP_NAME="Tir Lavigny"
ENV VITE_APP_NAME=$VITE_APP_NAME

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
