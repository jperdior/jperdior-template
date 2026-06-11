#!/bin/sh
# Blocks until the dev stack's HTTP-facing services (web, api, admin) actually
# respond, then exits 0. Used by `make start` so it returns once the stack is
# usable instead of tailing logs forever.
#
# A service counts as "up" when it returns any HTTP status that is NOT a
# connection failure (000) or a gateway error (502/503/504). Next.js dev routes
# compile on first request and the API may answer 404/401 on `/` — all of those
# still mean the server is listening, which is what we care about here.
#
# All three services (web, api, admin) are reached through Traefik on the same
# port using Host header routing, as configured in
# ops/docker/docker-compose.dev.yml.
#
# Host/port overrides (defaults match docker-compose.dev.yml):
#   TRAEFIK_PORT        host port for Traefik web entrypoint   [80]
#   STACK_WAIT_TIMEOUT  per-service timeout in seconds         [180]

set -e

TRAEFIK_PORT="${TRAEFIK_PORT:-80}"
TIMEOUT="${STACK_WAIT_TIMEOUT:-180}"

BASE_URL="http://127.0.0.1:${TRAEFIK_PORT}/"

# service|url|host-header
SERVICES="
web|${BASE_URL}|localhost
api|${BASE_URL}|api.localhost
admin|${BASE_URL}|admin.localhost
"

probe() {
  url="$1"
  host="$2"
  # `-o /dev/null -w %{http_code}` always prints a 3-digit code (000 on no
  # response), so capture it rather than relying on curl's exit status — curl
  # can exit non-zero (e.g. --max-time) *after* a status is known, and a `||`
  # fallback would concatenate onto the already-printed code.
  if [ -n "$host" ]; then
    code="$(curl -s -o /dev/null -w '%{http_code}' -H "Host: $host" --max-time 5 "$url" 2>/dev/null)"
  else
    code="$(curl -s -o /dev/null -w '%{http_code}' --max-time 5 "$url" 2>/dev/null)"
  fi
  [ -n "$code" ] && echo "$code" || echo 000
}

wait_for() {
  name="$1"
  url="$2"
  host="$3"
  printf '  waiting for %-6s' "$name"
  elapsed=0
  while [ "$elapsed" -lt "$TIMEOUT" ]; do
    code="$(probe "$url" "$host")"
    case "$code" in
      000|502|503|504)
        printf '.'
        sleep 2
        elapsed=$((elapsed + 2))
        ;;
      *)
        printf ' up (HTTP %s)\n' "$code"
        return 0
        ;;
    esac
  done
  printf ' TIMEOUT after %ss (last HTTP %s)\n' "$TIMEOUT" "$code"
  return 1
}

echo "Waiting for the stack to come up..."
failed=""
for entry in $SERVICES; do
  [ -z "$entry" ] && continue
  name="${entry%%|*}"
  rest="${entry#*|}"
  url="${rest%%|*}"
  host="${rest#*|}"
  if ! wait_for "$name" "$url" "$host"; then
    failed="$failed $name"
  fi
done

if [ -n "$failed" ]; then
  echo ""
  echo "Stack did not fully come up. Unhealthy:$failed"
  echo "Inspect with: make start-logs   (or: make logs)"
  exit 1
fi

echo ""
echo "Stack is up: web (http://localhost), api (http://api.localhost), admin (http://admin.localhost)"
