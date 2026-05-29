# syntax=docker/dockerfile:1.7
# -----------------------------------------------------------------------------
# Shared image for the `api` (php-fpm) and `worker` (messenger:consume) services.
# Only the CMD differs at runtime — same code, same extensions, same dependencies.
# -----------------------------------------------------------------------------

# ---- Stage 1: Composer dependencies (no dev) ----
FROM composer:2 AS composer

# ---- Stage 2: Builder ----
FROM php:8.4-fpm-alpine AS builder

# Install build deps + runtime extensions.
RUN apk add --no-cache --virtual .build-deps \
        autoconf gcc g++ make pkgconfig linux-headers \
        icu-dev libpq-dev oniguruma-dev libzip-dev \
    && apk add --no-cache \
        icu libpq libzip git bash \
    && docker-php-ext-install -j$(nproc) \
        intl \
        opcache \
        pdo_pgsql \
        bcmath \
        zip \
    && pecl install redis apcu \
    && docker-php-ext-enable redis apcu \
    && apk del .build-deps

COPY --from=composer /usr/bin/composer /usr/bin/composer

WORKDIR /app

# Copy minimum needed to install vendor deps (cached layer).
COPY apps/api/composer.json apps/api/composer.lock* apps/api/symfony.lock* ./apps/api/
COPY packages/shared-kernel-php ./packages/shared-kernel-php
COPY packages/tenancy-php ./packages/tenancy-php

# Install vendor without scripts/auto-scripts (cache:clear needs the source).
RUN cd apps/api \
    && composer install --no-dev --no-scripts --no-autoloader --prefer-dist --no-interaction

# Copy source and finalize autoload.
COPY apps/api/bin ./apps/api/bin
COPY apps/api/config ./apps/api/config
COPY apps/api/migrations ./apps/api/migrations
COPY apps/api/public ./apps/api/public
COPY apps/api/src ./apps/api/src
COPY apps/api/templates ./apps/api/templates
RUN cd apps/api && composer dump-autoload --optimize --classmap-authoritative --no-dev

# ---- Stage 3: Runtime ----
FROM php:8.4-fpm-alpine AS runtime

# Runtime-only shared libraries (no compilers, no -dev headers).
RUN apk add --no-cache icu-libs libpq libzip bash

# Copy compiled PHP extension .so files and their ini configs from the builder.
COPY --from=builder /usr/local/lib/php/extensions/ /usr/local/lib/php/extensions/
COPY --from=builder /usr/local/etc/php/conf.d/ /usr/local/etc/php/conf.d/

# Production php.ini (overridable via volume in dev).
COPY ops/docker/api/php.ini /usr/local/etc/php/conf.d/zz-app.ini
COPY ops/docker/api/php-fpm.conf /usr/local/etc/php-fpm.d/zz-app.conf

# Composer present in runtime too — dev workflow runs `composer install` inside the container.
COPY --from=composer /usr/bin/composer /usr/bin/composer

# App code + vendor from the builder.
WORKDIR /app/apps/api
COPY --from=builder /app /app

# Symfony writes cache/log here.
RUN mkdir -p var/cache var/log config/jwt \
    && chown -R www-data:www-data var config/jwt

USER www-data

EXPOSE 9000

CMD ["php-fpm", "--nodaemonize"]
