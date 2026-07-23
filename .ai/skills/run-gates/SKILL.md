---
name: run-gates
description: Run the CI verification gate — dispatch each in-scope gate (lint-api, test-api, lint-web, test-web, build-web, test-e2e, openapi-drift) as a parallel subagent, scoped to the diff so only the tests relevant to what changed run. Lint/build gates run standalone without postgres/api; the PHP test gate (test-api) uses the shared test stack; the web e2e gate (test-e2e) uses its own isolated disposable stack. Triggers on "run the gate", "run gates", "verify the branch", "ci gate".
---

# Run the Verification Gate

The single source of truth for running this repo's CI gate. Other skills (`check-and-commit`, `code-review`, `implement-spec`) invoke this rather than re-describing the commands.

**Three principles:** run gates in **parallel**; run only what the diff touches — both at the *gate* level (skip the PHP gates for a frontend-only change) and at the *test* level (a change to one bounded context runs that context's tests, not the whole PHPUnit suite); and **always tear the stacks down when the run finishes** (Step 6) — a gate run that leaves a per-worktree stack up is an incomplete gate run. Leaked stacks accumulate across worktrees and fill the disk.

## Superpowers Integration

- `superpowers:dispatching-parallel-agents` — the gates are independent; dispatch each as its own subagent concurrently.
- `superpowers:verification-before-completion` — read each subagent's COMPLETE output and confirm 0 errors before reporting PASS. Evidence before assertions.

## The gates

Every **lint and build gate** runs standalone in an ephemeral container (`docker compose run --rm --no-deps`) and needs **no postgres/api** — the PHP lint gates (`lint-api`, `lint-shared-kernel`) use an ephemeral `api` container exactly like the JS gates use a node container. The **PHP test gate** (`test-api`, which runs PHPUnit against a live DB) uses the shared per-worktree headless test stack (`up-test` → postgres + api). The **web e2e gate** (`test-e2e`) brings up its **own** isolated, disposable per-worktree stack (postgres + redis + api + nginx + web, no host ports) and manages its own lifecycle — no `make start`, and it coexists with the dev stack and other worktrees' e2e stacks. All `make` targets run inside containers — never invoke `pnpm`/`tsc`/`eslint`/`php` on the host.

| Gate | Command | Stack | Checks |
|------|---------|-------|--------|
| lint-api | `make lint-api` | standalone | PHPStan + cs-fixer + deptrac |
| test-api | `make test-api` / `make test-api ARG="--filter …"` | shared | PHPUnit unit + functional |
| lint-web | `make lint-web` | standalone | tsc + ESLint (web + admin + packages) |
| test-web | `make test-web` | standalone | JS unit tests (web vitest + admin) |
| build-web | `make build-web` | standalone | Production Next.js build (web + admin) |
| test-e2e | `make test-e2e` | isolated | Playwright auth journey (anon → signup → logout → login), both locales |
| openapi-drift | `make gen-api` + `git diff --exit-code -- apps/api/openapi.json packages/api-client-ts/src/types.gen.ts` | standalone | Committed OpenAPI artifacts match the code |

## Workflow

### 1. Scope to the diff — pick the gates AND narrow the tests

Run `git diff --name-only <base>...HEAD`, then map the changed paths:

**Backend — `apps/api/**`**
- Changed **one or more `apps/api/src/<Context>/`** (e.g. `User`, `Order`) → `lint-api` + `test-api` **scoped to those contexts only**: `make test-api ARG="tests/Functional/<Context>"` per touched context, or `make test-api ARG="--filter '<Ctx1>|<Ctx2>'"`. Do **not** run the whole PHPUnit suite for a single-context change.
- Changed **`apps/api/src/Shared/`, `packages/shared-kernel-php/`, `apps/api/config/`, or `apps/api/migrations/`** (cross-cutting — can break any context) → run the **full** `make test-api`.
- Changed `apps/api/**` in an OpenAPI-affecting way (routes, DTOs, Nelmio annotations) → also run **openapi-drift**: `make gen-api`, then `git diff --exit-code -- apps/api/openapi.json packages/api-client-ts/src/types.gen.ts`. A non-empty diff means the regenerated artifacts weren't committed — commit them. (CI's `openapi-drift` job runs the same check.)
- Changed the **auth/session surface** — `apps/api/src/User/`, the login/refresh/logout endpoints, or `packages/auth-server-ts/` — → also run **`test-e2e`**: the journey signs up, logs in, and logs out against the live API, so an auth-side backend change can break it with **no web diff at all**. Scope e2e by *auth impact*, not by which tree changed.

**Frontend — `apps/web/**` / `apps/admin/**` / `packages/{ui-react,api-client-ts}/**`**
- Always `lint-web` for any FE change.
- `test-web` runs the JS unit suite (web vitest + admin). Skip it only if the change is untestable-by-unit (pure markup/copy with no logic).
- `build-web` when UI/build output could change (any `apps/web`/`apps/admin` change).
- **`test-e2e`** when the change can affect the journey's flows — on the frontend: routing/middleware, auth pages (login/signup), the home page, or the shared header/nav/layout (and see the auth/session **backend** trigger above). For a change confined to an unrelated page that already has a vitest unit, e2e is optional (say so in the report).

**Docs / specs / CI-yaml only** → no code gates; say "no gates in scope".

**When unsure, or a shared/config/cross-cutting file changed → run everything.**

### 2. Bring up the shared stack once if `test-api` is in scope

```sh
make up-test
```
Only `test-api` needs this — it runs PHPUnit against a live DB. The lint/build gates (PHP *and* JS) are standalone and need no such step. The web **`test-e2e`** gate manages its **own** isolated stack (`make test-e2e` brings it up, resets its DB from scratch, and runs) — it needs neither `up-test` nor `make start`, but it does **not** stop itself: you tear it down with `make stop-e2e` in Step 6.

Whatever stack this run brings up, **you own tearing it down** — see Step 6. Neither the individual `make test-api` gate nor `make test-e2e` stops its stack on its own (only the aggregate `make test` target self-stops), so an ungated `up-test` or `test-e2e` leaks a running stack for this worktree.

### 3. Dispatch one subagent per in-scope gate, all in a single message (parallel)

Each subagent runs exactly one `make` invocation (with the scoping args chosen in step 1), reads the COMPLETE output, and returns `PASS`/`FAIL` plus the failing lines and the command name.

### 4. Collect results

Every in-scope gate MUST report `PASS`. Any `FAIL` is blocking — a finding to fix or flag, even if "pre-existing on `main`" (if it fails on the branch, CI fails).

### 5. Report

A compact table of gate → PASS/FAIL with evidence, and note explicitly what was scoped out (e.g. "test-api scoped to `User`; other contexts not run").

### 6. Tear down the stacks — ALWAYS, pass or fail

This step is **not optional and is not skipped on failure**. As soon as the gate results are collected (Step 4), tear down every stack this run brought up, before you report or fix anything:

```sh
make stop-test    # REQUIRED if `test-api` (or any DB-backed gate) ran — you ran `make up-test`, so you stop it
make stop-e2e     # REQUIRED if `test-e2e` ran — it does not self-stop; this drops its disposable DB volume
```

- Run `make stop-test` whenever `test-api` was in scope — i.e. whenever Step 2 ran `make up-test`. It stops+removes this worktree's headless test stack (the cache volumes survive on purpose for the next run).
- Run `make stop-e2e` whenever `test-e2e` ran — it leaves its isolated stack up otherwise.
- A lint/build/`test-web`-only run brings up **no** stack — nothing to tear down.
- Tear down even when a gate **FAILED**. Fix on a fresh run; don't leave the stack up "to iterate" — re-running `make test-api` transparently brings the stack back up.
- After tearing down, confirm nothing leaked with `docker ps --filter name=-test- --format '{{.Names}}'` — it should not list this worktree's stack.

## Never

- **Never** finish a gate run with `test-api` or `test-e2e` leaving its per-worktree stack up — Step 6 (`make stop-test` / `make stop-e2e`) is mandatory, pass or fail. Leaked stacks are what fill the disk.
- Don't run the frontend gates through `up-test` — they are standalone by design; routing them through the PHP stack wastes time and reintroduces the coupling this split removed.
- Don't run the **full** `test-api` when the diff is confined to a single context — scope it with `ARG` (that's the point). Escalate to the full run only for shared/config/cross-cutting changes, or when unsure.
- Don't run `test-e2e` for a diff that can't affect the auth journey (pure backend, docs, or an isolated non-journey page with unit coverage) — but DO run it for routing/middleware/auth/home/nav changes.
- Don't claim PASS without fresh output from the current run.

## Output

```text
Verification gate ({N} gates, scoped to diff)
  make lint-api                 PASS
  make test-api ARG="…User"     PASS ({M} unit tests — User only)
  make lint-web                 PASS
  make test-web                 PASS ({M} unit tests)
  make build-web                PASS
  (scoped out: full PHP suite — change confined to User)
  teardown                      make stop-test ✓ (no leaked stack)
```
