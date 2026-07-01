---
name: run-gates
description: Run the CI verification gate — dispatch each gate (lint-api, test-api, lint-web, test-web, build-web) as a parallel subagent. All lint/build gates run standalone without postgres/api; only the PHP test gate (test-api) uses the shared test stack. Triggers on "run the gate", "run gates", "verify the branch", "ci gate".
---

# Run the Verification Gate

The single source of truth for running this repo's CI gate. Other skills (`check-and-commit`, `code-review`, `implement-spec`) invoke this rather than re-describing the commands.

## Superpowers Integration

- `superpowers:dispatching-parallel-agents` — the gates are independent; dispatch each as its own subagent concurrently.
- `superpowers:verification-before-completion` — read each subagent's COMPLETE output and confirm 0 errors before reporting PASS. Evidence before assertions.

## The gates

Every **lint and build gate** runs standalone in an ephemeral container (`docker compose run --rm --no-deps`) and needs **no postgres/api** — the PHP lint gates (`lint-api`, `lint-shared-kernel`) use an ephemeral `api` container exactly like the JS gates use a node container. Only the **PHP test gate** (`test-api`, which runs PHPUnit against a live DB) uses the shared per-worktree headless test stack (`up-test` → postgres + api). All `make` targets run inside containers — never invoke `pnpm`/`tsc`/`eslint`/`php` on the host.

| Gate | Command | Stack | Checks |
|------|---------|-------|--------|
| lint-api | `make lint-api` | standalone | PHPStan + cs-fixer + deptrac |
| test-api | `make test-api` | shared | PHPUnit unit + functional |
| lint-web | `make lint-web` | standalone | tsc + ESLint (web + admin + packages) |
| test-web | `make test-web` | standalone | JS unit tests (web vitest + admin) |
| build-web | `make build-web` | standalone | Production Next.js build (web + admin) |

## Workflow

1. **Scope the gate to the diff.** Run `git diff --name-only <base>...HEAD`.
   - Touched `apps/api/**` or `packages/shared-kernel-php/**` → include the **PHP gates**.
   - Touched `apps/web/**`, `apps/admin/**`, or `packages/{ui-react,api-client-ts}/**` → include the **frontend gates**.
   - `build-web` is required only when UI/build output could change (any `apps/web` or `apps/admin` change); skip it for backend-only diffs.
   - When unsure, run everything.

2. **If `test-api` is in scope, bring the shared stack up once** so the PHP test subagent doesn't race on starting it:
   ```sh
   make up-test
   ```
   The lint and build gates (PHP *and* JS) are standalone and need no such step — only `test-api` (PHPUnit against a live DB) does.

3. **Dispatch one subagent per in-scope gate, all in a single message (parallel).** Each subagent:
   - runs exactly one `make` target,
   - reads the COMPLETE output,
   - returns `PASS` or `FAIL` plus the failing lines (and the command name).

4. **Collect results.** Every in-scope gate MUST report `PASS`. Any `FAIL` is blocking — a finding to fix or flag, even if "pre-existing on `main`" (if it fails on the branch, CI fails).

5. **Report** a compact table of gate → PASS/FAIL with evidence.

## Never

- Don't run the frontend gates through `up-test` — they are standalone by design; routing them through the PHP stack wastes time and reintroduces the coupling this split removed.
- Don't claim PASS without fresh output from the current run.

## Output

```
Verification gate ({N} gates, scoped to diff)
  make lint-api   PASS
  make test-api   PASS
  make lint-web   PASS
  make test-web   PASS ({M} unit tests)
  make build-web  PASS
```
