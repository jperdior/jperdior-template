#!/usr/bin/env bash
# Production build for every app. Run from monorepo root.
set -euo pipefail

cd "$(git rev-parse --show-toplevel)"

echo "==> Composer (no-dev) for apps/api"
( cd apps/api && composer install --no-dev --no-interaction --optimize-autoloader --classmap-authoritative )

echo "==> pnpm install"
pnpm install --frozen-lockfile=false

echo "==> Build apps/web"
pnpm -C apps/web build

echo "==> Build apps/admin"
pnpm -C apps/admin build

echo "==> All builds OK"
