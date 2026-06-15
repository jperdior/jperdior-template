---
name: lint-php
description: Run all PHP quality checks locally — PHPStan, php-cs-fixer, and deptrac — on apps/api and packages/shared-kernel-php. Triggers on "run phpstan", "run php lint", "php quality", "check php", "phpstan", "cs-fixer", "fix php formatting", "php static analysis".
---

# PHP Quality Checks

Run the full PHP lint suite that mirrors the `php-lint` CI job.

## CRITICAL: Always run inside Docker containers

**Never** invoke `php`, `vendor/bin/phpstan`, or any PHP binary directly on the host machine.
All PHP tooling runs inside the `jperdior-api-1` container via `make` targets.

## Worktree setup

`make lint-api` auto-starts a headless, per-worktree test stack that mounts the worktree's code. No manual stack management is needed — the commands work from anywhere inside the worktree without `make start` or `make stop`.

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

Or run only PHPStan via docker exec directly:

```bash
docker exec jperdior-api-1 php vendor/bin/phpstan analyse -c phpstan.dist.neon --memory-limit=512M --no-progress
```

### 2 — php-cs-fixer (dry-run)

```bash
docker exec jperdior-api-1 php vendor/bin/php-cs-fixer fix --dry-run --diff
```

To auto-fix (only when the user says "fix it"):

```bash
make lint-fix
```

### 3 — deptrac

```bash
docker exec jperdior-api-1 php vendor/bin/deptrac analyse --no-progress
```

## Error handling

- **PHPStan errors**: read each error, locate the file/line, fix the root cause. Never use `@phpstan-ignore` unless the error is a confirmed false positive in a third-party type stub.
- **CS-fixer diff**: apply `make lint-fix`. CS fixer is authoritative — don't argue with it.
- **Deptrac violation**: a bounded-context boundary was crossed. Fix the import, not the rule.

## Pre-PR gate

Run `make lint-api && make test-api` and confirm both pass before offering to create a PR.
