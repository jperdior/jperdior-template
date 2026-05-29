#!/usr/bin/env bash
# Lint matrix: PHPStan + cs-fixer + deptrac on PHP, tsc + eslint on JS.
# Run from monorepo root.
set -euo pipefail

cd "$(git rev-parse --show-toplevel)"

failures=()
run() {
  echo "==> $1"
  if ! eval "$2"; then
    failures+=("$1")
  fi
}

run "PHPStan (apps/api)"               "cd apps/api && vendor/bin/phpstan analyse -c phpstan.dist.neon --memory-limit=512M --no-progress"
run "PHPStan (shared-kernel-php)"      "cd packages/shared-kernel-php && vendor/bin/phpstan analyse -c phpstan.dist.neon --no-progress"
run "PHPStan (tenancy-php)"            "cd packages/tenancy-php && vendor/bin/phpstan analyse -c phpstan.dist.neon --no-progress"
run "php-cs-fixer (apps/api)"          "cd apps/api && vendor/bin/php-cs-fixer fix --dry-run --diff"
run "deptrac (apps/api)"               "cd apps/api && vendor/bin/deptrac analyse --no-progress"
run "Typecheck (JS workspaces)"        "pnpm -r --filter './apps/*' --filter './packages/*' typecheck"
run "ESLint (apps)"                    "pnpm -r --filter './apps/*' lint"

if (( ${#failures[@]} > 0 )); then
  echo
  echo "Lint failures:"
  printf ' - %s\n' "${failures[@]}"
  exit 1
fi
