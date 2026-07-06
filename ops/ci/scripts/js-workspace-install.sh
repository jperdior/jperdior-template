#!/bin/sh
# Idempotent pnpm workspace install for the standalone JS gates (lint-web,
# test-web, build-web, gen-api). Runs INSIDE the node:22-alpine gate container,
# so it is POSIX sh — no bash, no git.
#
# The gates use `docker compose run --rm` (a fresh container each time), but the
# node_modules and corepack volumes persist per worktree. A plain `pnpm install`
# still re-runs corepack activation plus pnpm's lockfile supply-chain
# verification (~15s+) on every invocation. This script skips the install
# entirely when the lockfile has not changed since the last successful install,
# so an unchanged tree pays only container startup.
#
# Usage: js-workspace-install.sh <web|admin>
set -e

app="${1:?usage: js-workspace-install.sh <web|admin>}"
repo=/repo
marker="$repo/apps/$app/node_modules/.deps-hash"

case "$app" in
  web)   filter="@jperdior/web..." ;;
  admin) filter="@jperdior/admin..." ;;
  *)     echo "js-workspace-install: unknown app '$app'" >&2; exit 2 ;;
esac

# Corepack shim; pnpm resolves to the version in package.json's packageManager
# field. COREPACK_HOME is a persisted volume, so the download happens at most once
# per worktree instead of every run. Runs unconditionally — the gate command after
# this script needs pnpm on PATH even when the install itself is skipped.
corepack enable >/dev/null 2>&1 || true

# The lockfile fully determines node_modules; the workspace file covers catalog
# and override changes that also invalidate it.
hash=$(cat "$repo/pnpm-lock.yaml" "$repo/pnpm-workspace.yaml" 2>/dev/null | md5sum | cut -d' ' -f1)

if [ -d "$repo/apps/$app/node_modules" ] && [ "$(cat "$marker" 2>/dev/null)" = "$hash" ]; then
  echo "[$app] dependencies unchanged — skipping pnpm install"
  exit 0
fi

echo "[$app] installing dependencies (first run or lockfile changed)…"
pnpm install --filter "$filter" --filter "./packages/*"
printf '%s' "$hash" > "$marker"
