#!/bin/sh
# Blocks until the headless PHP test stack (postgres + api) has finished installing
# dependencies, then exits 0. Used by `make up-test` so the PHP CI-gate targets only
# run once the api container can actually serve `exec`'d commands.
#
# The JS workspace gates (lint-web / test-web / build-web) run standalone in ephemeral
# node containers and need nothing from this stack, so they are not waited on here.
#
# Readiness criteria:
#   api    -> /tmp/stack-ready exists. The api startup command removes this sentinel
#             at the start of every (re)start and only re-creates it after composer
#             install + migrations fully succeed. A file-based check on the vendor
#             dir is NOT reliable: vendor is a named volume, so vendor/bin/phpstan
#             persists across runs and would "pass" on a crash-looping container
#             (which then yields the dreaded `exec` Error 137). The sentinel lives
#             on container-local /tmp and resets each start, so it tracks the
#             outcome of *this* boot.
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
api|test -f /tmp/stack-ready
"

# Current cumulative RestartCount for a service (0 if the container doesn't exist
# yet). Captured as a per-service baseline before we start polling so that a stale
# count left by a *previous* `make lint-api` (which never tears the stack down) is
# not mistaken for a crash in *this* run.
restart_count() {
  cid=$(eval "${DOCKER_COMPOSE_TEST} ps -q $1" 2>/dev/null || true)
  [ -z "$cid" ] && { echo 0; return; }
  rc=$(docker inspect -f '{{.RestartCount}}' "$cid" 2>/dev/null || echo 0)
  echo "${rc:-0}"
}

# Decide whether a service is crash-looping *in this run*. All test-stack services
# use `restart: unless-stopped`, so every crash bumps RestartCount. We require at
# least TWO new restarts since the baseline:
#   - a container recovering after the user fixed the error does exactly ONE
#     restart (baseline+1) and then stays up → must NOT be flagged;
#   - a genuine crash loop keeps climbing (baseline+2, +3, …) → flagged.
# A healthy or just-recovered container never reaches +2, so wait_for falls through
# to the readiness probe and returns "ready".
# Returns 0 (true) if crash-looping, 1 (false) otherwise.
crashed() {
  name="$1"
  baseline="$2"
  restarts=$(restart_count "$name")
  [ "$(( ${restarts:-0} - ${baseline:-0} ))" -ge 2 ]
}

# Dump the crashed container's logs and classify the root cause.
report_crash() {
  name="$1"
  cid=$(eval "${DOCKER_COMPOSE_TEST} ps -q ${name}" 2>/dev/null || true)
  oom=$(docker inspect -f '{{.State.OOMKilled}}' "$cid" 2>/dev/null || true)
  restarts=$(docker inspect -f '{{.RestartCount}}' "$cid" 2>/dev/null || true)
  logs=$(eval "${DOCKER_COMPOSE_TEST} logs --tail=200 --no-color ${name}" 2>&1 || true)

  echo ""
  echo "=================================================================="
  echo " '${name}' container is crash-looping during startup (${restarts:-?} restarts)."
  echo "=================================================================="
  printf '%s\n' "$logs" | tail -n 80
  echo "------------------------------------------------------------------"

  if [ "$oom" = "true" ]; then
    echo ">> TRUE OOM: Docker killed '${name}' (State.OOMKilled=true)."
    echo ">> Raising the container/PHP memory limit is the correct fix here."
  elif printf '%s' "$logs" | grep -qiE 'Allowed memory size of [0-9]+ bytes exhausted'; then
    echo ">> PHP memory_limit exhausted. This is usually a runaway loop/recursion,"
    echo ">> NOT an undersized limit. Read the stack trace above BEFORE raising memory_limit."
  elif printf '%s' "$logs" | grep -qiE 'PHP (Parse|Fatal) error|syntax error|Uncaught|Cannot (autowire|resolve|instantiate)'; then
    echo ">> PHP CODE ERROR  —  *** NOT an out-of-memory problem ***."
    echo ">> Do NOT raise memory limits and do NOT relaunch the container."
    echo ">> Fix the PHP error shown above, then re-run 'make lint-api'."
  else
    echo ">> '${name}' exited unexpectedly. The cause is in the logs above —"
    echo ">> it is almost certainly NOT memory. Inspect before changing limits."
  fi
  echo "=================================================================="
}

# Returns: 0 ready, 1 timed out, 2 crash-looping (report already printed).
wait_for() {
  name="$1"
  check="$2"
  # Baseline the restart count BEFORE polling so a crash-loop left by a previous
  # `make lint-api` run (the stack is not torn down between runs) is not counted.
  baseline=$(restart_count "$name")
  printf '  waiting for %-6s' "$name"
  elapsed=0
  while [ "$elapsed" -lt "$TIMEOUT" ]; do
    # Crash check FIRST: a crash-looping container is down most of the time, so the
    # readiness file-check could otherwise false-pass during a brief up-window and
    # mask the crash. Checking crashes first lets the classified banner win.
    # A recovering container (after a fix) does exactly +1 restart and stays up, so
    # the ≥2 threshold does not mistake it for a crash — readiness then passes.
    if crashed "$name" "$baseline"; then
      printf '\n'
      report_crash "$name"
      return 2
    fi
    if eval "${DOCKER_COMPOSE_TEST} exec -T ${name} sh -c '${check}'" </dev/null >/dev/null 2>&1; then
      printf ' ready\n'
      return 0
    fi
    printf '.'
    sleep 3
    elapsed=$((elapsed + 3))
  done
  printf ' TIMEOUT after %ss\n' "$TIMEOUT"
  echo "  Slow install? Check logs: ${DOCKER_COMPOSE_TEST} logs --tail=100 ${name}"
  return 1
}

echo "Waiting for the test stack to install dependencies..."
failed=""
crashed_service=""
# Iterate line-by-line: check commands contain spaces, so a `for` word-split would
# shatter each entry. Feed the lines via a here-doc and read name|check per line.
# `if wait_for …; then rc=0; else rc=$?; fi` captures the exit code while staying
# safe under `set -e` (the call sits in an if-condition, so a non-zero return does
# not abort the script).
while IFS='|' read -r name check; do
  [ -z "$name" ] && continue
  if wait_for "$name" "$check"; then
    rc=0
  else
    rc=$?
  fi
  if [ "$rc" -eq 2 ]; then
    # Crash already reported by wait_for/report_crash. Stop immediately so that
    # banner is the last thing on screen, not buried under further waiting.
    crashed_service="$name"
    break
  elif [ "$rc" -ne 0 ]; then
    failed="$failed $name"
  fi
done <<EOF
$SERVICES
EOF

if [ -n "$crashed_service" ]; then
  exit 1
fi

if [ -n "$failed" ]; then
  echo ""
  echo "Test stack did not become ready. Still installing/unhealthy:$failed"
  echo "Inspect with: ${DOCKER_COMPOSE_TEST} logs --tail=100"
  exit 1
fi

echo ""
echo "PHP test stack ready (deps installed): api"
