---
name: lint-js
description: Run all frontend quality checks locally — TypeScript typecheck and ESLint — across apps/web, apps/admin, and packages. Triggers on "run eslint", "js lint", "frontend quality", "typecheck", "check typescript", "ts errors", "lint frontend", "check frontend".
---

# Frontend Quality Checks

Run the full JS/TS lint suite that mirrors the `js-lint` CI job.

## CRITICAL: Always run inside Docker containers

**Never** invoke `pnpm`, `tsc`, or `eslint` directly on the host machine.
All JS tooling runs inside the `jperdior-web-1` / `jperdior-admin-1` containers via `make` targets.

## Worktree setup

`make lint-web` auto-starts a headless, per-worktree test stack that mounts the worktree's code. No manual stack management is needed — the commands work from anywhere inside the worktree without `make start` or `make stop`.

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

### TypeScript (all apps + packages)

```bash
docker exec jperdior-web-1 pnpm -r --filter './apps/web' --filter './packages/*' typecheck
docker exec jperdior-admin-1 pnpm -C apps/admin typecheck
```

### ESLint (apps only)

```bash
docker exec jperdior-web-1 pnpm -C apps/web lint
docker exec jperdior-admin-1 pnpm -C apps/admin lint
```

## Error handling

- **TypeScript errors**: fix the types in the source file. Never use `@ts-ignore` or `as any` — find the correct type.
- **ESLint errors**: fix the source. If a rule fires on a legitimate pattern (e.g. `react/no-unescaped-entities`), escape the character properly (`&apos;`, `&quot;`) — don't disable rules.

## Pre-PR gate

Run `make lint-web` and confirm it passes before offering to create a PR.
