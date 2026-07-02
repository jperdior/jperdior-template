# Architecture Deepening — auth module, verified seams, consolidated mappings

## TLDR

Six deepening refactors from the 2026-07-02 architecture review, shipped as one branch: extract a shared auth session module (`@jperdior/auth-server`) for `apps/web` + `apps/admin`, make the OpenAPI seam verified in CI, make the User-context port seams real with in-memory/fake adapters and unit tests, consolidate the reset-password exception→HTTP mapping in the `ExceptionListener`, extract the duplicated admin user dialogs into one module, and make the Makefile the single author of the CI gate commands.

Behaviour-preserving with **one deliberate exception**: the `next` redirect parameter in sign-in is hardened against open redirects (absolute/protocol-relative URLs are rejected and fall back to the default redirect). All API routes, response bodies, event IDs, and the cookie contract are unchanged.

Constraint: the Handler → UseCase split stays as-is per `docs/adr/0001-keep-handler-usecase-split.md`.

## Overview

The 2026-07-02 architecture review (three parallel explorers over `apps/api`, the Next.js apps, and the cross-tier seams) found the template's friction concentrated in duplicated session knowledge, unverified generated interfaces, hypothetical port seams, and twice-written mappings. This spec batches the six accepted candidates. Candidate 2 (flattening Handler→UseCase) was explicitly rejected — see ADR-0001.

As a **template**, the reference patterns this branch establishes (auth module, test doubles, exception map, CI single-author) compound into every project cloned from it.

## Problem Statement

- `lib/auth.ts` is byte-identical in web and admin; `middleware.ts` is ~85% copied; the token contract (cookies `at`/`rt`, refresh protocol, baseUrl fallbacks — inconsistently `http://api:8080` vs `http://nginx:80`) is re-learned by ~10 files. Auth flows have zero test coverage. The sign-in `next` param is redirected without same-origin validation (open redirect).
- `apps/api/openapi.json` and `packages/api-client-ts/src/types.gen.ts` are **gitignored generated artifacts** (`.gitignore:24-25`); neither exists in the repo. `packages/api-client-ts/AGENTS.md` claims a `/types` export and a CI freshness gate — **neither exists**. Backend response changes drift silently to the frontends.
- Five User-context ports (`UserRepository`, `PasswordRecoveryTokenRepository`, `PasswordHasherInterface`, `PasswordRecoveryEmailSender`, `RefreshTokenRevoker`) have exactly one adapter and no test double; the fastest test of any use case boots Symfony + Postgres. Domain aggregates have no unit tests (`tests/Unit/` is empty; CI already runs the empty suite).
- Exception→HTTP status mapping is split: `Shared/Presentation/Http/ExceptionListener.php` handles the generic cases (`DomainException`→409 `CONFLICT` etc.), while `ResetPasswordWithTokenController` hand-rolls three catch blocks (404 `password_recovery_token_not_found`, 422 `password_recovery_token_expired`, 422 `password_recovery_token_already_used`) with fixed messages. (`SignUpController` and `AdminDeleteUserController` have **no** catch blocks — `UserAlreadyExists` and `CannotDeleteSelf` already resolve to 409 `CONFLICT` via the generic fallback; the cannot-delete-self path has **no functional test**.)
- Three admin user dialogs (EditRoles, ForceReset, Delete) are ~95% duplicated across `UserActionsMenu.tsx` (158 LOC) and `UserDetailActions.tsx` (171 LOC); `UserDetailActions` additionally has a Restore dialog.
- The CI gate commands are written twice — `.github/workflows/ci.yml` (runner-native composer/pnpm) and the Makefile (containerised) — plus a **dead third copy** in `ops/ci/scripts/` that nothing invokes. Local green and CI green can drift.

## Proposed Solution

| # | Work item | Tier | Shape |
|---|-----------|------|-------|
| 1 | Auth session module | frontend | New package `packages/auth-server-ts` (`@jperdior/auth-server`) owning the whole sign-in flow via factories; web + admin become thin config adapters |
| 3 | OpenAPI drift gate | cross-tier | Standalone (no-DB) spec export; un-gitignore + commit `openapi.json` and `types.gen.ts`; CI job regenerates and fails on diff |
| 4 | Real port seams | backend | Keep all five ports; fake adapters under `apps/api/tests/Doubles/`; unit tests for aggregates + use cases |
| 5 | Exception→HTTP module | backend | Per-context status-map providers feeding the Shared `ExceptionListener`; the reset-password controller loses its catch blocks |
| 6 | Admin user-dialog module | frontend | Extract 4 dialogs into `apps/admin/src/components/users/dialogs/`; both callers thin |
| 7 | CI gate single author | ops | Each CI job invokes Makefile targets; `ops/ci/scripts/` deleted |

## Architecture

- **Bounded context(s) affected**: User (Presentation + tests only), Shared (Presentation). No Domain or Application production code changes.
- **New aggregates / value objects**: none.
- **Buses used**: unchanged (command/query/event wiring untouched).
- **Cross-context interaction**: none new. The exception-map mechanism is explicitly designed so `Shared` never imports `User\*` (deptrac: Shared → User is forbidden; User → Shared is allowed).
- **New packages**: `packages/auth-server-ts` (`@jperdior/auth-server`).

### Item 1 — `@jperdior/auth-server`

The deep module: everything a page or action needs to know about sessions, behind four factories. Internals (refresh protocol, `me()` sequencing, redirect sanitising) are invisible to callers. Cookie names **stay owned by** `@jperdior/api-client-ts/server` (`ACCESS_TOKEN_COOKIE`/`REFRESH_TOKEN_COOKIE`); this package imports them — one source of truth, no relocation.

```
packages/auth-server-ts/
├── package.json        ← name @jperdior/auth-server; exports { ".": src/index.ts };
│                          deps: @jperdior/api-client-ts (workspace:*), zod; peerDeps: next;
│                          scripts: test (vitest run), typecheck (tsc --noEmit)
├── tsconfig.json       ← mirrors packages/api-client-ts
├── vitest.config.ts    ← node environment; mocked next/headers + next/navigation
└── src/
    ├── index.ts        ← public interface (below)
    ├── cookies.ts      ← persistTokens / clearTokens / isAuthenticated (logic from apps/*/lib/auth.ts;
    │                      constants imported from @jperdior/api-client-ts/server)
    ├── signIn.ts       ← createSignInAction factory + sanitizeNext()
    ├── signOut.ts      ← createSignOutAction factory
    ├── middleware.ts    ← createAuthMiddleware factory
    └── __tests__/
```

Also: add `packages/auth-server-ts` to `pnpm-workspace.yaml`, and extend the Makefile so the standalone JS gates cover it — `test-web` adds `pnpm -C packages/auth-server-ts test`; typecheck coverage needs no Makefile change because `lint-web` and CI's `js-lint` already run `pnpm -r --filter "./packages/*" typecheck`, which picks the new package up via the workspace.

Public interface:

```ts
export function createSignInAction(config?: {
  authorize?: (me: CurrentUser) => true | string;        // string = error shown to user; runs BEFORE cookies persist
  postSignInRedirect?: (me: CurrentUser | null, next: string) => string;
  defaultRedirect?: string;                               // default '/dashboard'
}): (prev: SignInState, formData: FormData) => Promise<SignInState>;

export function createSignOutAction(config?: { redirectTo?: string }): () => Promise<void>;

export function createAuthMiddleware(config: {
  publicPaths: string[];
  publicPrefixes?: string[];                              // e.g. ['/reset-password/']
  loginPath?: string;                                     // default '/login'
}): (req: NextRequest) => NextResponse;                    // sync, like the current middleware

export { persistTokens, clearTokens, isAuthenticated };
export type { SignInState };                               // { error?: string } — identical shape to today's LoginState
```

Sign-in flow inside the factory (order is the behavioural contract):

1. Parse credentials (zod: email + password min 8 + optional `next`).
2. **Sanitise `next`** — deliberate hardening: accept only values starting with `/` and not `//`; anything else falls back to `defaultRedirect`. (Today `redirect(next)` honours absolute URLs — an open redirect. This is the spec's one behaviour change.)
3. `client.login()` — invalid credentials → `{ error }`.
4. `me()` once (best-effort when no `authorize` is configured — mirrors web's current tolerance).
5. `authorize(me)` if configured — failure calls **`clearTokens()`** (preserving admin's defensive wipe of any pre-existing session cookies on a shared browser) and returns `{ error }` without persisting anything.
6. `persistTokens(token, refresh_token)` — attributes `httpOnly, sameSite:'lax', secure: prod, path:'/'` are an invariant locked by test (TC-07d).
7. `redirect(postSignInRedirect?.(me, next) ?? next ?? defaultRedirect)` — web passes the `mustResetPassword → '/reset-password'` rule here.

App adapters. `'use server'` files may only export async functions, so the adapter is an explicit async wrapper (the factory product is module-private); the `LoginState` type is re-exported as an alias so `LoginForm.tsx` imports keep compiling:

```ts
// apps/web/src/app/login/actions.ts
'use server';
const signIn = createSignInAction({
  postSignInRedirect: (me, next) => me?.mustResetPassword ? '/reset-password' : next,
});
export type LoginState = SignInState;
export async function loginAction(prev: LoginState, formData: FormData): Promise<LoginState> {
  return signIn(prev, formData);
}

// apps/admin/src/app/login/actions.ts — same wrapper with
//   authorize: (me) => me.roles.includes('ROLE_ADMIN') || 'This account does not have admin access.'

// apps/{web,admin}/src/middleware.ts
export const middleware = createAuthMiddleware({ publicPaths: [...], publicPrefixes: [...] });
export const config = { matcher: [...] };                 // stays static per Next.js requirement
```

`apps/*/src/lib/auth.ts` is deleted. **All files touching the session surface are rewired to `@jperdior/auth-server`** — the six current `@/lib/auth` importers plus both middlewares (which inline cookie checks today):

| App | File | Symbols |
|-----|------|---------|
| web | `app/login/actions.ts` | becomes the factory adapter above |
| web | `app/signup/actions.ts` | `persistTokens` (stays a bespoke action; only the import moves) |
| web | `app/(app)/layout.tsx` | `isAuthenticated`, `clearTokens` (sign-out) |
| web | `middleware.ts` | becomes `createAuthMiddleware` adapter |
| admin | `app/login/actions.ts` | becomes the factory adapter above |
| admin | `app/page.tsx` | `isAuthenticated` |
| admin | `app/(admin)/layout.tsx` | `isAuthenticated`, `clearTokens` (sign-out) |
| admin | `middleware.ts` | becomes `createAuthMiddleware` adapter |

The signup / forgot-password / reset-password actions stop constructing `createApiClient({ baseUrl: ... })` inline and use `apiClient()` from `@jperdior/api-client-ts/server` (works unauthenticated), removing every scattered baseUrl fallback. The sign-in factory's authenticated `me()` call cannot use `apiClient()` (the fresh token is deliberately not persisted yet), so `server.ts` additionally **exports its baseUrl resolution as `API_BASE_URL`** — one source of truth for the env fallback chain; the refresh protocol and rotation handling in `server.ts` are behaviourally untouched.

Sign-in ordering note: web currently persists cookies **before** the `me()` check; the factory persists **after**. Outcome-equivalent for the user (cookies are set in both paths before redirect); the admin path is unchanged. `mustResetPassword` remains a UX redirect, not a hard gate — pre-existing, documented here so nobody mistakes the refactor for enforcement.

Doc sync for this phase: `apps/web/AGENTS.md`, `apps/admin/AGENTS.md` (auth sections), and reconcile `docs/auth.md`'s `SameSite=Strict` claim with the actual `lax` (pre-existing drift, corrected while we're here).

### Item 3 — OpenAPI drift gate

- **Remove `.gitignore:24-25`** (`apps/*/openapi.json`, `packages/api-client-ts/src/types.gen.ts`) — without this the entire mechanism is inert: `git add` no-ops and `git diff --exit-code` never sees the files. This is the phase's first deliverable.
- `make gen-api` becomes a **standalone gate** (ephemeral `docker compose run --rm --no-deps` api container, like `lint-api`): `nelmio:apidoc:dump` boots the kernel and reads routes/attributes — expected DB-free. Empirically verified during implementation; if the dump does need the DB, fall back to the current `up-test` dependency (Makefile-only change; the CI job installs runner-native either way).
- `apps/api/openapi.json` is committed (**reviewed for internal URLs / server blocks before the first commit**); `packages/api-client-ts/src/types.gen.ts` is generated and committed (the file does not exist today).
- Add the missing `./types` entry to `packages/api-client-ts` `package.json` `exports`, making the `@jperdior/api-client-ts/types` claim in its AGENTS.md true.
- New CI job `openapi-drift`: `make gen-api` → `git diff --exit-code -- apps/api/openapi.json packages/api-client-ts/src/types.gen.ts`. The job calls the **same make target developers run after modifying the API** (root AGENTS.md already mandates `make gen-api` after any OpenAPI-affecting change) — generation stays single-authored per Item 7's principle, and if the standalone dump ever falls back to the `up-test`-backed path, CI inherits it automatically through the target.
- **Non-goal**: rewriting `apiClient.ts`'s handwritten interfaces to consume `types.gen.ts`. The gate makes drift visible; migrating consumers to generated types is a follow-up spec.
- Rollback coupling: the committed artifacts and the `openapi-drift` job revert **together** (a partial revert leaves the gate red or vacuous).

### Item 4 — Real port seams

Fakes in `apps/api/tests/Doubles/` (namespace `App\Tests\Doubles`; `composer.json` already maps `App\Tests\ → tests/` in `autoload-dev`, and the prod service scan covers only `../src/`, so fakes cannot leak into the container):

| Double | Satisfies | Behaviour |
|--------|-----------|-----------|
| `InMemoryUserRepository` | `UserRepository` | array-backed; honours soft-delete filtering like the Doctrine adapter |
| `InMemoryPasswordRecoveryTokenRepository` | `PasswordRecoveryTokenRepository` | array-backed |
| `FakePasswordHasher` | `PasswordHasherInterface` | deterministic reversible "hash" |
| `SpyPasswordRecoveryEmailSender` | `PasswordRecoveryEmailSender` | records sent messages |
| `SpyRefreshTokenRevoker` | `RefreshTokenRevoker` | records the `Email` values passed to `revokeAllFor()` |
| `SpyEventBus` | `EventBus` (shared kernel) | records published domain events — required by `SignUpUseCase` |
| `NullTransaction` | `TransactionInterface` (`Jperdior\SharedKernel\Domain\Repository`) | no-op `begin()` / `commit()` / `rollback()` / `clear()` |

Clock: **reuse the existing** `Jperdior\SharedKernel\Infrastructure\Clock\FrozenClock` (already ships `travel()`); no new clock double.

New unit tests in `apps/api/tests/Unit/` (suite already wired in `phpunit.xml.dist` and CI; `tests/Doubles` is **not** added as a suite):

- **Domain**: `User` (register emits `user.account.created` via `pullDomainEvents`, changePassword, softDelete/restore, promote, role invariants), `PasswordRecoveryToken` (validate, expiry, already-used), value objects (`Email`, `PlainPassword`, `Role`, `UserId` reject invalid input at construction).
- **Application** (through the port interfaces, fakes injected): `SignUpUseCase` (asserts the event reaches `SpyEventBus`), `RequestPasswordRecoveryUseCase`, `ResetPasswordWithTokenUseCase` (happy path + each domain failure + asserts revocation via `SpyRefreshTokenRevoker`).

Functional tests remain the integration safety net; none are removed.

### Item 5 — Exception→HTTP mapping

New in `App\Shared\Presentation\Http`:

```php
interface ExceptionStatusMapProvider
{
    /** @return array<class-string<\Throwable>, array{status:int, code:string, message:string}> */
    public function map(): array;
}
```

- Semantics: **exact-class lookup** (`$exception::class` as array key — no `instanceof` walking), providers merged at listener construction via tagged iterator (`_instanceof` tag `app.exception_status_map` in `config/services.yaml`); a duplicate class key across providers throws a `LogicException` at construction (misconfiguration, fail fast). The merged map is checked **before** the generic `match`; `message` comes **from the map** (the current controller returns fixed messages, not `getMessage()`).
- `App\User\Presentation\Http\UserExceptionStatusMap` contains **only the three reset-password token exceptions**, strings copied verbatim from `ResetPasswordWithTokenController`:
  - `PasswordRecoveryTokenNotFound → {404, 'password_recovery_token_not_found', 'Token not found.'}`
  - `PasswordRecoveryTokenExpired → {422, 'password_recovery_token_expired', 'Token expired.'}`
  - `PasswordRecoveryTokenAlreadyUsed → {422, 'password_recovery_token_already_used', 'Token already used.'}`
- **No entries for `UserAlreadyExists` or `CannotDeleteSelf`** — they have no catch blocks today; the generic `DomainException → 409 CONFLICT` fallback already produces the correct (current) response, and adding entries would silently change the wire `code`. A new functional test locks the untested cannot-delete-self path at its **current** behaviour (409 + `code: CONFLICT`) before the listener changes land (TC-06b).
- `ResetPasswordWithTokenController`'s try/catch is deleted (the only controller that has one); it shrinks to dispatch-and-return.
- Boundary-clean: `User → Shared` (allowed); `Shared` only knows the interface.
- Token-oracle note (deliberate, documented for template cloners): 404-vs-422 on reset-password distinguishes token states. Acceptable **here** because the token is a 96-hex-char bearer secret behind a 10/min/IP rate limit — do **not** copy this status split for low-entropy identifiers. Contrast with forgot-password's always-204 (BR-U05).
- Doc sync: `apps/api/AGENTS.md`'s "never catch a domain exception in a controller unless…" rule gets its escape hatch replaced: context-specific statuses now live in the context's `ExceptionStatusMapProvider`.

### Item 6 — Admin user-dialog module

```
apps/admin/src/components/users/dialogs/
├── EditRolesDialog.tsx
├── ForceResetDialog.tsx
├── DeleteUserDialog.tsx
├── RestoreUserDialog.tsx
└── __tests__/
```

Each dialog owns its open-state trigger contract: `{ user, open, onClose, action }` where `action` is the existing Server Action from `users/actions.ts`; the shared internal `ConfirmActionDialog` owns pending state, error display, close-on-success, and the ds-rules `Cmd/Ctrl+Enter` confirm shortcut. `UserActionsMenu.tsx` keeps offering its three dialogs; `UserDetailActions.tsx` offers all four (the Restore asymmetry is current behaviour and stays). Both callers keep only menu/button chrome. No visual or behavioural change.

### Item 7 — CI gate single author

- `.github/workflows/ci.yml` jobs map to Makefile targets (current job names in parentheses): `php-lint` → `make lint-api && make lint-shared-kernel`; **`php-tests-unit` + `php-tests-functional` merge into one `php-tests`** → `make test-api` (one container runs both suites); `js-lint` → `make lint-web`; `js-tests-unit` → renamed `js-tests` → `make test-web`; `js-build` → `make build-web`.
- **Branch protection**: required-status-check names live in GitHub settings, not the repo. The PR description must list the rename map (`php-tests-unit`/`php-tests-functional` → `php-tests`, `js-tests-unit` → `js-tests`) so the repo admin updates required checks at merge time — otherwise the requirements silently orphan.
- The Postgres service container, env blocks (`DATABASE_URL`, `APP_SECRET`, `JWT_*` — all throwaway CI values, no real secrets), JWT keypair, and migration steps in `ci.yml` are deleted — the headless test stack owns all of them, identically to local. Constraint: no new privileged containers, no docker.sock bind-mounts.
- The **entire `ops/ci/scripts/` directory** is deleted (`lint.sh`, `test.sh`, `build.sh`, `install.sh` — dead code; `ci.yml` never invoked them). Doc sync: `docs/ops.md` and `ops/AGENTS.md` references removed.
- Wall-clock cost is measured on the PR. Acceptance threshold: if total CI time exceeds **2×** the current baseline, the functional-test job may keep a runner-native fallback (documented as a deliberate second author with a comment pointing at the Makefile as source of truth).
- Rollback coupling: reverting `ci.yml` and restoring `ops/ci/scripts/` happen in the same revert (they land in the same phase commits).

## Data Models

No entity, `*Model`, or schema changes. **No migrations.**

## API Contracts

No routes added, removed, or renamed. No request/response field changes. The reset-password error contract (produced by `ExceptionListener` after item 5, byte-identical to today's controller responses — status, `code`, **and** fixed `message`) is:

```jsonc
// POST /auth/reset-password with an unknown token — response 404 (unchanged)
{ "code": "password_recovery_token_not_found", "message": "Token not found." }
```

Contract locks: `ItReturnsNotFoundForUnknownTokenTest`, `ItRejectsExpiredTokenTest`, `ItRejectsAlreadyUsedTokenTest` (assert status + `code`) must pass unchanged; TC-06b is added to lock cannot-delete-self (409 + `CONFLICT`) which today has no test.

## Frontend Plan

- No new routes. `login/actions.ts` and `middleware.ts` in both apps are rewritten as adapters; `lib/auth.ts` deleted in both; all eight importer files rewired (see Item 1 table).
- No Server/Client boundary changes: actions stay `'use server'` files (async-wrapper pattern); dialogs stay `'use client'` (they already are — state + handlers).
- No form, loading, error, or i18n changes. `LoginForm.tsx` in both apps keeps compiling via the `LoginState` type alias.
- New Vitest coverage in `packages/auth-server-ts` and `apps/admin` (see Integration Coverage). `make test-web` / `make lint-web` gain the package's test/typecheck steps; the CI `js-tests` job picks them up when Phase 6 routes it through `make test-web` (until then, Phase 4 adds the one-line `pnpm -C packages/auth-server-ts test` to the existing CI job).

## Phasing

All phases land on this branch; one PR. Each phase ends with `make lint && make test` green and includes its doc sync (`/sync-context-docs` for touched contexts / package AGENTS.md files).

| Phase | Item | Goal | Deliverable |
|-------|------|------|-------------|
| 0 | — | Spec + ADR committed | `.ai/specs/2026-07-02-architecture-deepening.md`, `docs/adr/0001-*.md` |
| 1 | 5 | Exception→HTTP mapping consolidated | TC-06b lock test first; `ExceptionStatusMapProvider` + `UserExceptionStatusMap` (3 token entries); reset-password catch blocks deleted; functional tests green unchanged |
| 2 | 4 | Port seams real | `tests/Doubles/*` (incl. `SpyEventBus`, `NullTransaction` with 4 no-op methods; `FrozenClock` reused); Unit tests for aggregates, VOs, 3 use cases; `make test-api` green |
| 3 | 6 | Admin dialogs deduplicated | `components/users/dialogs/*` + tests; both menus thin |
| 4 | 1 | Auth session module | `packages/auth-server-ts` (package.json/tsconfig/vitest + workspace registration) + tests; 8 importer files rewired; `lib/auth.ts` deleted; inline baseUrls removed; Makefile `test-web`/`lint-web` extended; `next` sanitisation |
| 5 | 3 | OpenAPI seam verified | `.gitignore:24-25` removed; standalone `gen-api`; committed reviewed `openapi.json` + `types.gen.ts`; `./types` export added; `openapi-drift` CI job |
| 6 | 7 | CI single author | `ci.yml` jobs call make targets (rename map in PR body for branch protection); `ops/ci/scripts/` deleted; docs synced; timing measured |

## Risks & Impact Review

| Risk | Severity | Affected area | Mitigation | Residual |
|------|----------|---------------|------------|----------|
| Error response bodies drift when the catch blocks move to the listener | High | API error contract | Map carries status + `code` + fixed `message` verbatim; only the 3 token exceptions get entries; 3 existing tests + new TC-06b lock the contract | Low |
| `nelmio:apidoc:dump` needs a DB connection after all | Medium | Phase 5 | Fall back to `up-test`-backed `gen-api`; the `openapi-drift` job calls the make target, so it inherits the fallback unchanged | Low |
| Next.js rejects the adapter exports | Low | Phase 4 | Async-wrapper is the **primary** pattern (not a fallback); type exports are erased and allowed | Negligible |
| `next`-param hardening breaks a legitimate absolute-URL redirect | Low | sign-in UX | Deliberate change; middleware only ever writes relative paths into `next`; TC-07c covers accepted/rejected shapes | Negligible |
| Admin defensive `clearTokens()` on authorize-reject lost in the move | Medium | shared-browser session hygiene | Factory calls `clearTokens()` on authorize failure; TC-07b asserts pre-existing cookies are cleared | Negligible |
| Cookie attributes silently weakened in the move | Medium | session security | TC-07d asserts `httpOnly`, `sameSite=lax`, `secure` (prod), `path=/` on persist | Low |
| Web sign-in persists cookies after `me()` instead of before — behaviour delta if `me()` throws | Low | apps/web login | Factory treats `me()` failure as non-fatal when no `authorize` configured (persists + redirects, as today) | Negligible |
| Docker-in-runner makes CI slower | Medium | Phase 6 | Measure on PR; 2× threshold with documented runner-native fallback for the functional job only | Medium (accepted) |
| Branch-protection required checks orphaned by CI job renames | Medium | merge gating | Rename map in PR body; repo admin updates required checks at merge | Low (manual step) |
| Committed `openapi.json` churns in every API PR | Low | DX | That is the mechanism working; `make gen-api` is standalone and fast | Accepted |
| In-memory fakes drift from Doctrine adapter semantics (soft-delete filter) | Medium | Unit-test fidelity | Fakes replicate the documented query invariants; functional tests still cover the real adapter | Low |

Undo: phases 1–4 revert cleanly commit-by-commit; phases 5 and 6 have **coupled reverts** (artifacts + gate; workflow + scripts) documented above. No migrations, no data changes.

## Integration Coverage

| Test ID | Type | Path | Asserts |
|---------|------|------|---------|
| TC-01 | PHPUnit Unit | `apps/api/tests/Unit/User/Domain/UserTest.php` | register emits `user.account.registered` (via `pullDomainEvents`); changePassword; softDelete/restore; role invariants |
| TC-02 | PHPUnit Unit | `apps/api/tests/Unit/User/Domain/PasswordRecoveryTokenTest.php` | validate happy path; expired; already-used |
| TC-03 | PHPUnit Unit | `apps/api/tests/Unit/User/Domain/ValueObject/*Test.php` | `Email`/`PlainPassword`/`Role`/`UserId` reject invalid input at construction |
| TC-04 | PHPUnit Unit | `apps/api/tests/Unit/User/Application/SignUpUseCaseTest.php` | creates user via fakes; event published to `SpyEventBus`; duplicate email throws `UserAlreadyExists` |
| TC-05 | PHPUnit Unit | `apps/api/tests/Unit/User/Application/ResetPasswordWithTokenUseCaseTest.php` | happy path; each token failure; revocation recorded by `SpyRefreshTokenRevoker` (per `Email`) |
| TC-06 | PHPUnit Functional | existing `apps/api/tests/Functional/User/**` | unchanged — the 3 reset-password body tests are the contract lock for Phase 1 |
| TC-06b | PHPUnit Functional | `.../AdminDeleteUser/ItRejectsSelfDeletionTest.php` (new) | cannot-delete-self returns 409 + `code: CONFLICT` — locks the currently untested path **before** listener changes |
| TC-07a | Vitest | `packages/auth-server-ts/src/__tests__/signIn.test.ts` | bad credentials → error, no cookies; success persists then redirects; `mustResetPassword` redirect rule |
| TC-07b | Vitest | same file | authorize reject → error, nothing persisted, **pre-existing cookies cleared** |
| TC-07c | Vitest | same file | `next` sanitisation: `/x` accepted; `https://evil.tld`, `//evil.tld` → defaultRedirect |
| TC-07d | Vitest | same file | persisted cookies carry `httpOnly`, `sameSite=lax`, `secure` (prod), `path=/` |
| TC-08 | Vitest | `packages/auth-server-ts/src/__tests__/middleware.test.ts` | public paths/prefixes pass; missing cookies redirect to login with `next` param |
| TC-09 | Vitest + RTL | `apps/admin/src/components/users/dialogs/__tests__/*.test.tsx` | each dialog renders and confirm invokes its action |
| TC-10 | CI | `.github/workflows/ci.yml` `openapi-drift` job | regenerated `openapi.json` + `types.gen.ts` produce no diff |

## Backward Compatibility

- [x] No removed/renamed event IDs
- [x] No removed/renamed API routes
- [x] No removed response fields
- [x] No removed DB columns
- [x] Deprecation bridge added if any contract surface changed — the only deliberate change is rejecting absolute `next` redirect values (security hardening; no legitimate producer exists — middleware writes relative paths only)

## Final Compliance Report

| Gate | Verdict |
|------|---------|
| Boundary | PASS — Shared never imports User; the map provider inverts the dependency (User → Shared interface). Fakes live under `tests/` outside the deptrac `./src` scope. |
| Bus | PASS — controllers keep dispatching through CommandBus/QueryBus; item 5 only removes catch blocks. |
| Mapping | PASS — no domain entity gains ORM attributes; no persistence changes at all. |
| Validation | PASS — value-object construction untouched; TC-03 adds explicit coverage. |
| Idempotency | PASS — no subscribers/workers added or changed; `SpyEventBus` is test-only. |
| Auth | PASS — no endpoint auth changes; admin sign-in invariant (no cookies before authorize, clear on reject) preserved and unit-tested (TC-07b); open redirect closed (TC-07c). |
| Naming | PASS — no new aggregates/commands/events/tables. |
| DateTime | PASS — `FrozenClock` (existing, `DateTimeImmutable`) reused; no domain code changes. |
| Final readonly | PASS — new PHP classes (`UserExceptionStatusMap`, fakes) follow `final` (+ `readonly` where stateless). |
| strict_types | PASS — all new PHP files declare it. |
| Tests | PASS — TC-01…TC-10 across PHPUnit Unit/Functional, Vitest, and a CI gate; previously-untested cannot-delete-self path gains a lock test. |
| BC | PASS — no contract surface removed or renamed; the single deliberate behaviour change (absolute `next` rejected) is documented above. |

No new business rules introduced — `.ai/business-rules.md` unchanged.

## Changelog

| Date | Change |
|------|--------|
| 2026-07-03 | Phase 6 implemented — every CI job invokes Makefile targets (`php-lint`→lint-shared-kernel+lint-api, merged `php-tests`→test-shared-kernel+test-api, `js-lint`/`js-tests`/`js-build`→lint-web/test-web/build-web); new `test-shared-kernel` target closes a coverage gap (its PHPUnit suite previously ran only in CI); `ops/ci/scripts/` deleted; docs synced. Branch-protection rename map for the repo admin: `php-tests-unit` + `php-tests-functional` → `php-tests`, `js-tests-unit` → `js-tests`; new required checks `openapi-drift`. CI wall-clock measured on the PR run. |
| 2026-07-02 | Phase 5 implemented — `.gitignore` entries removed; `gen-api` standalone (empirically verified: nelmio dump needs no DB); `openapi.json` (reviewed: no servers block, no internal URLs) + real `types.gen.ts` committed; `./types` export added; `openapi-drift` CI job calls `make gen-api` + `git diff --exit-code`. |
| 2026-07-02 | Phase 4 implemented — `@jperdior/auth-server` package (signIn/signOut/middleware factories, next-param sanitisation, clearTokens-on-reject); both apps rewired (8 files), `lib/auth.ts` deleted, inline baseUrls removed; 15 package Vitest tests wired into `make test-web` + CI; `docs/auth.md` cookie paragraph corrected to reality (both tokens HttpOnly SameSite=Lax — the old text described a nonexistent Zustand store). |
| 2026-07-02 | Phase 3 implemented — four named dialogs over a shared `ConfirmActionDialog` under `apps/admin/src/components/users/dialogs/`; both callers thinned to dialog selection; 5 Vitest cases (TC-09). |
| 2026-07-02 | Phase 2 implemented — 7 fakes under `tests/Doubles/`, unit tests for `User`, `PasswordRecoveryToken`, VOs, and 3 use cases (33 unit tests, 65ms). TC-01 corrected: the actual event name is `user.account.registered`. |
| 2026-07-02 | Phase 1 implemented — `ExceptionStatusMapProvider` + `UserExceptionStatusMap` (3 token entries), TC-06b lock test, reset-password controller catch blocks removed; full PHP suite green (22 tests). |
| 2026-07-02 | Spec skeleton drafted; open questions pending. |
| 2026-07-02 | Q1–Q6 answered (all recommendations accepted); full design completed. |
| 2026-07-02 | `openapi-drift` CI job now invokes `make gen-api` directly (single-author principle applied to generation; user feedback). |
| 2026-07-02 | Revised per pre-implementation audit: corrected the catch-block premise (only reset-password controller has them; no map entries for `UserAlreadyExists`/`CannotDeleteSelf`), added `message` to the map interface with exact-class/fail-fast semantics, added `.gitignore` removal + artifact review to Phase 5, added `SpyEventBus`/fixed `NullTransaction`/reused `FrozenClock`, specified package plumbing + workspace + Makefile coverage, enumerated all 8 `lib/auth` importers + `LoginState` alias, preserved admin `clearTokens()` on reject, hardened `next` (deliberate change), documented CI job rename map + branch protection, deleted entire `ops/ci/scripts/`, documented coupled reverts and the 404/422 token-oracle rationale. |
