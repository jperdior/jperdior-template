#!/bin/sh
# Blocks until the isolated web-e2e stack (postgres + api + nginx + web) is ready to
# accept a test run, then exits 0. Used by `make test-e2e`.
#
# Readiness criteria:
#   api -> `doctrine:migrations:up-to-date` exits 0. This proves three things at once:
#          composer install finished (the console runs), the DB is reachable, and the
#          api's boot-time `bin/start` migrate has COMPLETED. Gating on this is what lets
#          the Makefile safely drop + re-migrate the schema next without racing bin/start.
#   web -> `next dev` answers 200 on http://localhost:3000 (also warms the `/` compile).
#
# The compose invocation is read from DOCKER_COMPOSE_E2E_STACK (the Makefile exports all
# variables via .EXPORT_ALL_VARIABLES), so this script targets the correct per-worktree
# project.
#
#   E2E_WAIT_TIMEOUT  per-service timeout in seconds  [600]

set -e

if [ -z "${DOCKER_COMPOSE_E2E_STACK}" ]; then
  echo "wait-for-e2e-stack.sh: DOCKER_COMPOSE_E2E_STACK is not set" >&2
  exit 1
fi

TIMEOUT="${E2E_WAIT_TIMEOUT:-600}"

# wait_for <description> <service> <command...> — poll until the command exits 0.
wait_for() {
  desc="$1"
  shift
  elapsed=0
  until ${DOCKER_COMPOSE_E2E_STACK} exec -T "$@" >/dev/null 2>&1; do
    elapsed=$((elapsed + 3))
    if [ "${elapsed}" -ge "${TIMEOUT}" ]; then
      echo "wait-for-e2e-stack.sh: timed out after ${TIMEOUT}s waiting for ${desc}" >&2
      exit 1
    fi
    sleep 3
  done
  echo "e2e stack: ${desc} ready"
}

wait_for "api (migrated)" api php bin/console doctrine:migrations:up-to-date
wait_for "web (next dev serving)" web wget -q --spider http://localhost:3000
