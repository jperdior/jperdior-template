#!/usr/bin/env bash
# Test matrix: PHPUnit (unit + functional) on every PHP workspace + pnpm test on JS workspaces.
# Functional tests need a Postgres reachable via DATABASE_URL — CI usually starts one as a service.
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

run "PHPUnit (shared-kernel-php)" "cd packages/shared-kernel-php && vendor/bin/phpunit"
run "PHPUnit (tenancy-php)"       "cd packages/tenancy-php && vendor/bin/phpunit"
run "PHPUnit (apps/api: Unit)"    "cd apps/api && php code/vendor/bin/phpunit --testsuite Unit -c code/phpunit.xml.dist"
run "PHPUnit (apps/api: Functional)" "cd apps/api && php code/vendor/bin/phpunit --testsuite Functional -c code/phpunit.xml.dist"
run "pnpm test (workspaces)"      "pnpm -r --filter './apps/*' --filter './packages/*' test"

if (( ${#failures[@]} > 0 )); then
  echo
  echo "Test failures:"
  printf ' - %s\n' "${failures[@]}"
  exit 1
fi
