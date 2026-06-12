#!/usr/bin/env bash
# Install Composer + pnpm dependencies for every workspace. Run from monorepo root.
set -euo pipefail

cd "$(git rev-parse --show-toplevel)"

echo "==> Composer install (apps/api)"
( cd apps/api && composer install --no-interaction --no-progress --prefer-dist )

echo "==> Composer install (packages/shared-kernel-php)"
( cd packages/shared-kernel-php && composer install --no-interaction --no-progress --prefer-dist )

echo "==> pnpm install (workspace)"
pnpm install --frozen-lockfile=false
