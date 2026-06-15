#!/bin/sh
# Blocks until the headless test stack (postgres + api + web + admin) has finished
# installing dependencies, then exits 0. Used by `make up-test` so the CI-gate
# targets only run once the containers can actually serve `exec`'d commands.
#
# Readiness criteria:
#   api    -> vendor/autoload.php exists (composer install finished)
#   web    -> apps/web/node_modules present (pnpm install finished)
#   admin  -> apps/admin/node_modules present (pnpm install finished)
#
# The compose invocation is passed in via the DOCKER_COMPOSE_TEST env var (the
# Makefile exports all variables), so this script targets the correct per-worktree
# project without re-deriving it.
#
#   STACK_WAIT_TIMEOUT  per-service timeout in seconds  [600]

set -e

if [ -z "${DOCKER_COMPOSE_TEST}" ]; then
  echo "wait-for-test-stack.sh: DOCKER_COMPOSE_TEST is not set" >&2
  exit 1
fi

TIMEOUT="${STACK_WAIT_TIMEOUT:-600}"

# service|ready-check-command (run inside the container via compose exec)
SERVICES="
api|test -f /app/apps/api/vendor/autoload.php
web|test -d /repo/apps/web/node_modules
admin|test -d /repo/apps/admin/node_modules
"

wait_for() {
  name="$1"
  check="$2"
  printf '  waiting for %-6s' "$name"
  elapsed=0
  while [ "$elapsed" -lt "$TIMEOUT" ]; do
    if eval "${DOCKER_COMPOSE_TEST} exec -T ${name} sh -c '${check}'" </dev/null >/dev/null 2>&1; then
      printf ' ready\n'
      return 0
    fi
    printf '.'
    sleep 3
    elapsed=$((elapsed + 3))
  done
  printf ' TIMEOUT after %ss\n' "$TIMEOUT"
  return 1
}

echo "Waiting for the test stack to install dependencies..."
failed=""
# Iterate line-by-line: check commands contain spaces, so a `for` word-split would
# shatter each entry. Feed the lines via a here-doc and read name|check per line.
while IFS='|' read -r name check; do
  [ -z "$name" ] && continue
  if ! wait_for "$name" "$check"; then
    failed="$failed $name"
  fi
done <<EOF
$SERVICES
EOF

if [ -n "$failed" ]; then
  echo ""
  echo "Test stack did not become ready. Still installing/unhealthy:$failed"
  echo "Inspect with: ${DOCKER_COMPOSE_TEST} logs --tail=100"
  exit 1
fi

echo ""
echo "Test stack ready (deps installed): api, web, admin"
