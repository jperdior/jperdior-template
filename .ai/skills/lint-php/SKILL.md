---
name: lint-php
description: Run all PHP quality checks locally — PHPStan, php-cs-fixer, and deptrac — on apps/api and packages/shared-kernel-php. Triggers on "run phpstan", "run php lint", "php quality", "check php", "phpstan", "cs-fixer", "fix php formatting", "php static analysis".
---

# PHP Quality Checks

Run the full PHP lint suite that mirrors the `php-lint` CI job.

## CRITICAL: Always run inside Docker containers

**Never** invoke `php`, `vendor/bin/phpstan`, or any PHP binary directly on the host machine.
All PHP tooling runs inside an ephemeral `api` container via `make` targets.

## Worktree setup

`make lint-api` runs **standalone** — no postgres, no shared stack. It's a single
`docker compose run --rm --no-deps` invocation of the `api` image that mounts the
worktree's code and reuses the per-worktree cached `api_vendor`/`api_var` volumes, so
`composer install` is a fast no-op once populated. No manual stack management is needed —
the command works from anywhere inside the worktree without `make start` or `make up-test`.

## Scope

| Package | Tools |
|---------|-------|
| `apps/api` | PHPStan level 8, php-cs-fixer, deptrac |
| `packages/shared-kernel-php` | PHPStan level 8 |

## Quick one-liner (from repo root or worktree root)

```bash
make lint-api
```

This runs PHPStan + php-cs-fixer dry-run + deptrac inside the API container in one shot.

## Step-by-step (if you need to isolate a failure)

### 1 — PHPStan

```bash
make lint-api   # includes PHPStan — check output for errors
```

There is no persistent, named `api` container to `docker exec` into for a standalone
gate — each `make lint-api` run is a fresh ephemeral container. Isolate a single tool by
adding a one-off target or by temporarily editing the `lint-api` recipe; don't reach for
`docker exec <container-name>`, since standalone gates don't have one.

### 2 — php-cs-fixer (dry-run)

`make lint-api` includes a `php-cs-fixer fix --dry-run --diff` step.

To auto-fix (only when the user says "fix it"):

```bash
make lint-fix
```

### 3 — deptrac

`make lint-api` includes a `deptrac analyse` step.

## Error handling

- **PHPStan errors**: read each error, locate the file/line, fix the root cause. Never use `@phpstan-ignore` unless the error is a confirmed false positive in a third-party type stub.
- **CS-fixer diff**: apply `make lint-fix`. CS fixer is authoritative — don't argue with it.
- **Deptrac violation**: a bounded-context boundary was crossed. Fix the import, not the rule.

## Pre-PR gate

Run `make lint-api && make test-api` and confirm both pass before offering to create a PR.
