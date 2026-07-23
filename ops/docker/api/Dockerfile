# syntax=docker/dockerfile:1.7
# -----------------------------------------------------------------------------
# Shared image for the `api` (FrankenPHP) and `worker` (messenger:consume)
# services. Same code, same extensions, same dependencies — only the runtime
# command differs. The `api` service runs `frankenphp run`; the `worker` service
# overrides the CMD with `php bin/console messenger:consume`.
#
# FrankenPHP (Caddy web server + embedded PHP) replaces the old php-fpm + nginx
# split: it terminates HTTP directly, so there is no FastCGI hop and no separate
# reverse-proxy container.
# -----------------------------------------------------------------------------

# ---- Composer binary ----
FROM composer:2 AS composer

# ---- Stage 1: Base — FrankenPHP + PHP extensions (shared by builder & runtime) ----
# Debian bookworm base: `install-php-extensions` builds every extension against
# glibc here, so there is no cross-libc ABI mismatch (the old image compiled
# extensions on Alpine/musl and copied the .so files — incompatible with Debian).
FROM dunglas/frankenphp:1-php8.4-bookworm AS base

# git + unzip are needed by Composer for VCS/dist installs. `install-php-extensions`
# pulls in each extension's own system libraries (libpq, icu, libzip, rabbitmq-c…).
RUN apt-get update \
    && apt-get install -y --no-install-recommends git unzip \
    && rm -rf /var/lib/apt/lists/* \
    && install-php-extensions \
        intl \
        opcache \
        pdo_pgsql \
        bcmath \
        zip \
        redis \
        apcu \
        amqp

COPY --from=composer /usr/bin/composer /usr/bin/composer

WORKDIR /app

# ---- Stage 2: Builder — vendor install + optimized autoload ----
FROM base AS builder

# Copy minimum needed to install vendor deps (cached layer).
COPY apps/api/composer.json apps/api/composer.lock* apps/api/symfony.lock* ./apps/api/
COPY packages/shared-kernel-php ./packages/shared-kernel-php

# Install vendor without scripts/auto-scripts (cache:clear needs the source).
RUN --mount=type=cache,target=/tmp/composer-cache \
    cd apps/api \
    && COMPOSER_CACHE_DIR=/tmp/composer-cache \
       composer install --no-dev --no-scripts --no-autoloader --prefer-dist --no-interaction

# Copy source and finalize autoload.
# `.env` is Symfony's committed, non-secret baseline (real values come from container env,
# which overrides it). Symfony's runtime bootstrap (SymfonyRuntime::bootEnv, reached via
# public/index.php + the FrankenPhpSymfony runtime) reads it on boot, so it must be present
# in the image. Copied explicitly (not `.env*`) so a developer's git-ignored `.env.local`
# can never leak into the image.
COPY apps/api/.env ./apps/api/.env
COPY apps/api/bin ./apps/api/bin
COPY apps/api/config ./apps/api/config
COPY apps/api/migrations ./apps/api/migrations
COPY apps/api/public ./apps/api/public
COPY apps/api/src ./apps/api/src
COPY apps/api/templates ./apps/api/templates
RUN cd apps/api && composer dump-autoload --optimize --classmap-authoritative --no-dev

# ---- Stage 3: Runtime ----
FROM base AS runtime

# Production php.ini (dev compose overlays php-dev.ini on top). Applied only in the runtime
# stage — it sets opcache.preload to a path under /app/apps/api, which does not exist in the
# builder while `composer install` runs.
COPY ops/docker/api/php.ini /usr/local/etc/php/conf.d/zz-app.ini
# Caddy/FrankenPHP server config — lives outside /app so the dev source mount
# (which mounts over /app/apps/api) never shadows it.
COPY ops/docker/api/Caddyfile /etc/frankenphp/Caddyfile

WORKDIR /app/apps/api

# App code + vendor from the builder.
COPY --from=builder /app /app

# Symfony writes cache/log here; Caddy/FrankenPHP writes its state under
# /data + /config. Make all of them writable by the non-root runtime user.
RUN mkdir -p var/cache var/log config/jwt /data /config \
    && chown -R www-data:www-data var config/jwt /data /config

# Non-root. The FrankenPHP binary ships `cap_net_bind_service`, so www-data can
# still bind the privileged port 80. `APP_RUNTIME` is intentionally NOT set here:
# the shared `worker` runtime must use Symfony's default runtime — worker mode is
# opted into on the `api` service definitions only (compose/k8s env).
USER www-data

EXPOSE 80

# Override the base image's default healthcheck (which curls the admin API on :2019 —
# disabled here via `admin off`). Hit the Caddy-served /healthz on :80 instead.
HEALTHCHECK --interval=10s --timeout=3s --start-period=40s --retries=3 \
    CMD curl -fsS http://localhost:80/healthz || exit 1

CMD ["frankenphp", "run", "--config", "/etc/frankenphp/Caddyfile"]
