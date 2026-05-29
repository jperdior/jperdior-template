---
name: lint-php
description: Run all PHP quality checks locally — PHPStan, php-cs-fixer, and deptrac — on apps/api and packages/shared-kernel-php. Triggers on "run phpstan", "run php lint", "php quality", "check php", "phpstan", "cs-fixer", "fix php formatting", "php static analysis".
---

# PHP Quality Checks

Run the full PHP lint suite that mirrors the `php-lint` CI job.

## Scope

| Package | Tools |
|---------|-------|
| `apps/api` | PHPStan level 8, php-cs-fixer, deptrac |
| `packages/shared-kernel-php` | PHPStan level 8 |

## Workflow

Run these commands in order. Stop and report errors before continuing to the next step.

### 1 — Shared kernel

```bash
cd packages/shared-kernel-php
composer install --no-interaction --no-progress --prefer-dist
vendor/bin/phpstan analyse -c phpstan.dist.neon --no-progress
```

### 2 — API: PHPStan

```bash
cd apps/api
composer install --no-interaction --no-progress --prefer-dist
vendor/bin/phpstan analyse -c phpstan.dist.neon --memory-limit=512M --no-progress
```

### 3 — API: php-cs-fixer

Dry-run first. Only auto-fix if the user explicitly asks.

```bash
# check (dry-run — what CI does)
cd apps/api && vendor/bin/php-cs-fixer fix --dry-run --diff

# auto-fix (only when user says "fix it" / "apply fixes")
cd apps/api && vendor/bin/php-cs-fixer fix
```

### 4 — API: deptrac

```bash
cd apps/api && vendor/bin/deptrac analyse --no-progress
```

## Error handling

- **PHPStan errors**: read each error, locate the file/line, fix the root cause. Never use `@phpstan-ignore` unless the error is a confirmed false positive in a third-party type stub.
- **CS-fixer diff**: apply the diff. CS fixer is authoritative — don't argue with it.
- **Deptrac violation**: a bounded-context boundary was crossed. Fix the import, not the rule.

## Quick one-liner (all checks, from repo root)

```bash
make lint-api
```

Or if the Makefile target is unavailable, chain all steps manually as above.
