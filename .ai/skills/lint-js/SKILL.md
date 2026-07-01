---
name: lint-js
description: Run all frontend quality checks locally — TypeScript typecheck and ESLint — across apps/web, apps/admin, and packages. Triggers on "run eslint", "js lint", "frontend quality", "typecheck", "check typescript", "ts errors", "lint frontend", "check frontend".
---

# Frontend Quality Checks

Run the full JS/TS lint suite that mirrors the `js-lint` CI job.

## CRITICAL: Always run inside Docker containers

**Never** invoke `pnpm`, `tsc`, or `eslint` directly on the host machine.
All JS tooling runs inside ephemeral `web` / `admin` containers via `make` targets.

## Worktree setup

`make lint-web` runs **standalone** — no postgres, no api, no shared stack. It's two
`docker compose run --rm --no-deps` invocations (one per app) that mount the worktree's
code and reuse the per-worktree cached `node_modules` volumes, so `pnpm install` is a fast
no-op once populated. No manual stack management is needed — the command works from
anywhere inside the worktree without `make start` or `make up-test`.

## Scope

| Target | Tool |
|--------|------|
| `apps/web`, `apps/admin`, `packages/*` | TypeScript (`tsc --noEmit`) |
| `apps/web`, `apps/admin` | ESLint |

## Quick one-liner (from repo root or worktree root)

```bash
make lint-web
```

This runs typecheck + ESLint for all apps inside the correct containers.

## Step-by-step (if you need to isolate a failure)

There is no persistent, named `web`/`admin` container to `docker exec` into for a
standalone gate — each `make lint-web` run is a fresh ephemeral container per app.
`make lint-web` already runs typecheck then lint for each app in sequence; isolate a
failure by reading which of the two `${JS_RUN}` lines in the Makefile's `lint-web` target
failed, not by exec-ing into a container that no longer persists.

## Error handling

- **TypeScript errors**: fix the types in the source file. Never use `@ts-ignore` or `as any` — find the correct type.
- **ESLint errors**: fix the source. If a rule fires on a legitimate pattern (e.g. `react/no-unescaped-entities`), escape the character properly (`&apos;`, `&quot;`) — don't disable rules.

## Pre-PR gate

Run `make lint-web` and confirm it passes before offering to create a PR.
