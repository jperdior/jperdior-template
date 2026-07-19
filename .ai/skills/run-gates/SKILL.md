---
name: run-gates
description: Run the CI verification gate — dispatch each in-scope gate (lint-api, test-api, lint-web, test-web, build-web) as a parallel subagent, scoped to the diff so only the tests relevant to what changed run. Lint/build gates run standalone without postgres/api; only the PHP test gate (test-api) uses the shared test stack. Triggers on "run the gate", "run gates", "verify the branch", "ci gate".
---

# Run the Verification Gate

The single source of truth for running this repo's CI gate. Other skills (`check-and-commit`, `code-review`, `implement-spec`) invoke this rather than re-describing the commands.

**Two principles:** run gates in **parallel**, and run only what the diff touches — both at the *gate* level (skip the PHP gates for a frontend-only change) and at the *test* level (a change to one bounded context runs that context's tests, not the whole PHPUnit suite).

## Superpowers Integration

- `superpowers:dispatching-parallel-agents` — the gates are independent; dispatch each as its own subagent concurrently.
- `superpowers:verification-before-completion` — read each subagent's COMPLETE output and confirm 0 errors before reporting PASS. Evidence before assertions.

## The gates

Every **lint and build gate** runs standalone in an ephemeral container (`docker compose run --rm --no-deps`) and needs **no postgres/api** — the PHP lint gates (`lint-api`, `lint-shared-kernel`) use an ephemeral `api` container exactly like the JS gates use a node container. Only the **PHP test gate** (`test-api`, which runs PHPUnit against a live DB) uses the shared per-worktree headless test stack (`up-test` → postgres + api). All `make` targets run inside containers — never invoke `pnpm`/`tsc`/`eslint`/`php` on the host.

| Gate | Command | Stack | Checks |
|------|---------|-------|--------|
| lint-api | `make lint-api` | standalone | PHPStan + cs-fixer + deptrac |
| test-api | `make test-api` / `make test-api ARG="--filter …"` | shared | PHPUnit unit + functional |
| lint-web | `make lint-web` | standalone | tsc + ESLint (web + admin + packages) |
| test-web | `make test-web` | standalone | JS unit tests (web vitest + admin) |
| build-web | `make build-web` | standalone | Production Next.js build (web + admin) |
| openapi-drift | `make gen-api` + `git diff --exit-code -- apps/api/openapi.json packages/api-client-ts/src/types.gen.ts` | standalone | Committed OpenAPI artifacts match the code |

## Workflow

### 1. Scope to the diff — pick the gates AND narrow the tests

Run `git diff --name-only <base>...HEAD`, then map the changed paths:

**Backend — `apps/api/**`**
- Changed **one or more `apps/api/src/<Context>/`** (e.g. `User`, `Order`) → `lint-api` + `test-api` **scoped to those contexts only**: `make test-api ARG="tests/Functional/<Context>"` per touched context, or `make test-api ARG="--filter '<Ctx1>|<Ctx2>'"`. Do **not** run the whole PHPUnit suite for a single-context change.
- Changed **`apps/api/src/Shared/`, `packages/shared-kernel-php/`, `apps/api/config/`, or `apps/api/migrations/`** (cross-cutting — can break any context) → run the **full** `make test-api`.
- Changed `apps/api/**` in an OpenAPI-affecting way (routes, DTOs, Nelmio annotations) → also run **openapi-drift**: `make gen-api`, then `git diff --exit-code -- apps/api/openapi.json packages/api-client-ts/src/types.gen.ts`. A non-empty diff means the regenerated artifacts weren't committed — commit them. (CI's `openapi-drift` job runs the same check.)

**Frontend — `apps/web/**` / `apps/admin/**` / `packages/{ui-react,api-client-ts}/**`**
- Always `lint-web` for any FE change.
- `test-web` runs the JS unit suite (web vitest + admin). Skip it only if the change is untestable-by-unit (pure markup/copy with no logic).
- `build-web` when UI/build output could change (any `apps/web`/`apps/admin` change).

**Docs / specs / CI-yaml only** → no code gates; say "no gates in scope".

**When unsure, or a shared/config/cross-cutting file changed → run everything.**

### 2. Bring up the shared stack once if `test-api` is in scope

```sh
make up-test
```
Only `test-api` needs this — it runs PHPUnit against a live DB. The lint/build gates (PHP *and* JS) are standalone and need no such step.

### 3. Dispatch one subagent per in-scope gate, all in a single message (parallel)

Each subagent runs exactly one `make` invocation (with the scoping args chosen in step 1), reads the COMPLETE output, and returns `PASS`/`FAIL` plus the failing lines and the command name.

### 4. Collect results

Every in-scope gate MUST report `PASS`. Any `FAIL` is blocking — a finding to fix or flag, even if "pre-existing on `main`" (if it fails on the branch, CI fails).

### 5. Report

A compact table of gate → PASS/FAIL with evidence, and note explicitly what was scoped out (e.g. "test-api scoped to `User`; other contexts not run").

## Never

- Don't run the frontend gates through `up-test` — they are standalone by design; routing them through the PHP stack wastes time and reintroduces the coupling this split removed.
- Don't run the **full** `test-api` when the diff is confined to a single context — scope it with `ARG` (that's the point). Escalate to the full run only for shared/config/cross-cutting changes, or when unsure.
- Don't claim PASS without fresh output from the current run.

## Output

```
Verification gate ({N} gates, scoped to diff)
  make lint-api                 PASS
  make test-api ARG="…User"     PASS ({M} unit tests — User only)
  make lint-web                 PASS
  make test-web                 PASS ({M} unit tests)
  make build-web                PASS
  (scoped out: full PHP suite — change confined to User)
```
