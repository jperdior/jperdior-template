# Architecture Deepening — auth module, verified seams, consolidated mappings

## TLDR

Six deepening refactors from the 2026-07-02 architecture review, shipped as one branch: extract a shared auth session module (`@jperdior/auth-server`) for `apps/web` + `apps/admin`, make the OpenAPI seam verified in CI, make the User-context port seams real with in-memory/fake adapters and unit tests, consolidate exception→HTTP mapping in the `ExceptionListener`, extract the duplicated admin user dialogs into one module, and make the Makefile the single author of the CI gate commands. **No behaviour changes** — every phase preserves the public API routes, response shapes, event IDs, and cookie contract.

Constraint: the Handler → UseCase split stays as-is per `docs/adr/0001-keep-handler-usecase-split.md`.

## Overview

The 2026-07-02 architecture review (three parallel explorers over `apps/api`, the Next.js apps, and the cross-tier seams) found the template's friction concentrated in duplicated session knowledge, unverified generated interfaces, hypothetical port seams, and twice-written mappings. This spec batches the six accepted candidates. Candidate 2 (flattening Handler→UseCase) was explicitly rejected — see ADR-0001.

As a **template**, the reference patterns this branch establishes (auth module, test doubles, exception map, CI single-author) compound into every project cloned from it.

## Problem Statement

- `lib/auth.ts` is byte-identical in web and admin; `middleware.ts` is ~85% copied; the token contract (cookies `at`/`rt`, refresh protocol, baseUrl fallbacks — inconsistently `http://api:8080` vs `http://nginx:80`) is re-learned by ~10 files. Auth flows have zero test coverage.
- `packages/api-client-ts/src/types.gen.ts` is a placeholder and `apps/api/openapi.json` is not committed. `packages/api-client-ts/AGENTS.md` claims "CI fails the PR if it's out of date" — **no such CI job exists**. Backend response changes drift silently to the frontends.
- Five User-context ports (`UserRepository`, `PasswordRecoveryTokenRepository`, `PasswordHasherInterface`, `PasswordRecoveryEmailSender`, `RefreshTokenRevoker`) have exactly one adapter and no test double; the fastest test of any use case boots Symfony + Postgres. Domain aggregates have no unit tests (`tests/Unit/` is empty; CI already runs the empty suite).
- Exception→HTTP status mapping exists twice: generically in `Shared/Presentation/Http/ExceptionListener.php` (DomainException→409 etc.), and as hand-rolled catch blocks in `ResetPasswordWithTokenController` (404/422/422), `SignUpController` (409), `AdminDeleteUserController` (409).
- Three admin user dialogs (EditRoles, ForceReset, Delete) are ~95% duplicated across `UserActionsMenu.tsx` (158 LOC) and `UserDetailActions.tsx` (171 LOC).
- The CI gate commands are written three times: `ops/ci/scripts/*`, `.github/workflows/ci.yml` (runner-native composer/pnpm), and the Makefile (containerised). Local green and CI green can drift.

## Proposed Solution

| # | Work item | Tier | Shape |
|---|-----------|------|-------|
| 1 | Auth session module | frontend | New package `packages/auth-server-ts` (`@jperdior/auth-server`) owning the whole sign-in flow via factories; web + admin become thin config adapters |
| 3 | OpenAPI drift gate | cross-tier | Standalone (no-DB) spec export; commit `openapi.json` + real `types.gen.ts`; CI job regenerates and fails on diff |
| 4 | Real port seams | backend | Keep all five ports; fake adapters under `apps/api/tests/Doubles/`; unit tests for aggregates + use cases |
| 5 | Exception→HTTP module | backend | Per-context status-map providers feeding the Shared `ExceptionListener`; controllers lose catch blocks |
| 6 | Admin user-dialog module | frontend | Extract 4 dialogs into `apps/admin/src/components/users/dialogs/`; both callers thin |
| 7 | CI gate single author | ops | Each CI job invokes exactly one Makefile target; `ops/ci/scripts/*` deleted |

## Architecture

- **Bounded context(s) affected**: User (Presentation + tests only), Shared (Presentation). No Domain or Application production code changes.
- **New aggregates / value objects**: none.
- **Buses used**: unchanged (command/query/event wiring untouched).
- **Cross-context interaction**: none new. The exception-map mechanism is explicitly designed so `Shared` never imports `User\*` (deptrac: Shared → User is forbidden; User → Shared is allowed).
- **New packages**: `packages/auth-server-ts` (`@jperdior/auth-server`), depends on `@jperdior/api-client-ts` + `next` (peer) + `zod`.

### Item 1 — `@jperdior/auth-server`

The deep module: everything a page or action needs to know about sessions, behind four factories. Internals (cookie names, refresh protocol, `me()` sequencing, baseUrl resolution) are invisible to callers.

```
packages/auth-server-ts/src/
├── index.ts          ← public interface (below)
├── cookies.ts        ← persistTokens / clearTokens / isAuthenticated (moved from apps/*/lib/auth.ts)
├── signIn.ts         ← createSignInAction factory
├── signOut.ts        ← createSignOutAction factory
├── middleware.ts     ← createAuthMiddleware factory
└── __tests__/        ← Vitest unit tests (mocked next/headers + api client)
```

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
}): (req: NextRequest) => NextResponse;

export { persistTokens, clearTokens, isAuthenticated };
export type { SignInState };
```

Sign-in flow inside the factory (order is the behavioural contract):

1. Parse credentials (zod: email + password min 8 + optional `next`).
2. `client.login()` — invalid credentials → `{ error }`.
3. `me()` once (best-effort when no `authorize` is configured — mirrors web's current tolerance).
4. `authorize(me)` if configured — failure returns `{ error }` **without persisting any cookie** (preserves the admin invariant from `apps/admin/AGENTS.md`: reject non-admin logins before persisting).
5. `persistTokens(token, refresh_token)`.
6. `redirect(postSignInRedirect?.(me, next) ?? next ?? defaultRedirect)` — web passes the `mustResetPassword → '/reset-password'` rule here.

App adapters (the entire per-app auth surface after this change):

```ts
// apps/web/src/app/login/actions.ts
'use server';
export const loginAction = createSignInAction({
  postSignInRedirect: (me, next) => me?.mustResetPassword ? '/reset-password' : next,
});

// apps/admin/src/app/login/actions.ts
'use server';
export const loginAction = createSignInAction({
  authorize: (me) => me.roles.includes('ROLE_ADMIN') || 'This account does not have admin access.',
});

// apps/{web,admin}/src/middleware.ts
export const middleware = createAuthMiddleware({ publicPaths: [...], publicPrefixes: [...] });
export const config = { matcher: [...] };                 // stays static per Next.js requirement
```

`apps/*/src/lib/auth.ts` is deleted; imports rewire to `@jperdior/auth-server`. The signup / forgot-password / reset-password actions stop constructing `createApiClient({ baseUrl: ... })` inline and use `apiClient()` from `@jperdior/api-client-ts/server` (works unauthenticated), removing every scattered baseUrl fallback.

Sign-in ordering note: web currently persists cookies **before** the `me()` check; the factory persists **after**. Outcome-equivalent for the user (cookies are set in both paths before redirect); the admin path is unchanged (already persisted after). Called out in Risks.

### Item 3 — OpenAPI drift gate

- `make gen-api` becomes a **standalone gate** (ephemeral `docker compose run --rm --no-deps` api container, like `lint-api`): `nelmio:apidoc:dump` boots the kernel and reads routes/attributes — no DB connection. If the dump proves to need the DB in practice, fall back to the current `up-test` dependency (Makefile-only change; CI job unaffected because it installs runner-native).
- `apps/api/openapi.json` is **committed**; `packages/api-client-ts/src/types.gen.ts` becomes the real `openapi-typescript` output (placeholder deleted).
- New CI job `openapi-drift`: setup-php + composer install (apps/api) → `php bin/console nelmio:apidoc:dump --format=json > apps/api/openapi.json` → pnpm install → `pnpm -C packages/api-client-ts gen` → `git diff --exit-code -- apps/api/openapi.json packages/api-client-ts/src/types.gen.ts`.
- **Non-goal**: rewriting `apiClient.ts`'s handwritten interfaces to consume `types.gen.ts`. The gate makes drift visible; migrating consumers to generated types is a follow-up spec.
- This makes the existing claim in `packages/api-client-ts/AGENTS.md` ("CI fails the PR if it's out of date") true.

### Item 4 — Real port seams

Fakes in `apps/api/tests/Doubles/` (namespace `App\Tests\Doubles`, autoloaded with the existing test namespace):

| Double | Satisfies | Behaviour |
|--------|-----------|-----------|
| `InMemoryUserRepository` | `UserRepository` | array-backed; honours soft-delete filtering like the Doctrine adapter |
| `InMemoryPasswordRecoveryTokenRepository` | `PasswordRecoveryTokenRepository` | array-backed |
| `FakePasswordHasher` | `PasswordHasherInterface` | deterministic reversible "hash" |
| `SpyPasswordRecoveryEmailSender` | `PasswordRecoveryEmailSender` | records sent messages |
| `SpyRefreshTokenRevoker` | `RefreshTokenRevoker` | records revoked user IDs |
| `FixedClock` | `ClockInterface` (shared kernel) | returns a fixed `DateTimeImmutable` |
| `NullTransaction` | `TransactionInterface` (shared kernel) | executes the callable directly |

New unit tests in `apps/api/tests/Unit/` (suite already wired in `phpunit.xml.dist` and CI):

- **Domain**: `User` (register emits `user.account.created`, changePassword, softDelete/restore, promote, role invariants), `PasswordRecoveryToken` (validate, expiry, already-used), value objects (`Email`, `PlainPassword`, `Role`, `UserId` reject invalid input at construction).
- **Application** (through the port interfaces, fakes injected): `SignUpUseCase`, `RequestPasswordRecoveryUseCase`, `ResetPasswordWithTokenUseCase` (happy path + each domain failure + asserts refresh tokens revoked via the spy).

Functional tests remain the integration safety net; none are removed.

### Item 5 — Exception→HTTP mapping

New in `App\Shared\Presentation\Http`:

```php
interface ExceptionStatusMapProvider
{
    /** @return array<class-string<\Throwable>, array{status:int, code:string}> */
    public function map(): array;
}
```

- `_instanceof` tag in `config/services.yaml` (`app.exception_status_map`); `ExceptionListener` receives them via tagged iterator and checks the merged map **before** its generic `match` (specific class beats `DomainException`→409 fallback).
- `App\User\Presentation\Http\UserExceptionStatusMap` provides: `PasswordRecoveryTokenNotFound → {404, …}`, `PasswordRecoveryTokenExpired → {422, …}`, `PasswordRecoveryTokenAlreadyUsed → {422, …}`, `UserAlreadyExists → {409, …}`, `CannotDeleteSelf → {409, …}` — `code` strings copied **verbatim from the current controller responses** during implementation so response bodies are byte-identical.
- The three controllers' try/catch blocks are deleted; controllers shrink to dispatch-and-return.
- Boundary-clean: `User → Shared` (allowed); `Shared` only knows the interface.
- The `apps/api/AGENTS.md` rule "never catch a domain exception in a controller unless transforming it to a specific HTTP status" gets its escape hatch replaced: specific statuses now live in the context's `ExceptionStatusMapProvider` (docs updated in the same phase).

### Item 6 — Admin user-dialog module

```
apps/admin/src/components/users/dialogs/
├── EditRolesDialog.tsx
├── ForceResetDialog.tsx
├── DeleteUserDialog.tsx
├── RestoreUserDialog.tsx
└── __tests__/
```

Each dialog owns its open-state trigger contract: `{ user, open, onOpenChange, action }` where `action` is the existing Server Action from `users/actions.ts`. `UserActionsMenu.tsx` and `UserDetailActions.tsx` keep only their menu/button chrome and which dialogs they offer. No visual or behavioural change.

### Item 7 — CI gate single author

- `.github/workflows/ci.yml` jobs map 1:1 to Makefile targets: `php-lint → make lint-api` (+ `make lint-shared-kernel`), `php-tests → make test-api` (Unit + Functional via the headless test stack), `js-lint → make lint-web`, `js-tests → make test-web`, `js-build → make build-web`.
- The Postgres service container, env blocks, JWT keypair, and migration steps in `ci.yml` are deleted — the test stack owns them, identically to local.
- `ops/ci/scripts/{lint,test,build}.sh` are deleted.
- Wall-clock cost is measured on the PR. Acceptance threshold: if total CI time exceeds **2×** the current baseline, the functional-test job may keep a runner-native fallback (documented in the PR as a deliberate second author with a comment pointing at the Makefile as source of truth).

## Data Models

No entity, `*Model`, or schema changes. **No migrations.**

## API Contracts

No routes added, removed, or renamed. No request/response field changes. The error contract (produced by `ExceptionListener` after item 5, identical to today's controller responses) is:

```jsonc
// e.g. POST /auth/password-reset with an unknown token — response 404
{ "code": "NOT_FOUND", "message": "…" }   // exact code/message strings preserved from current controllers
```

Existing functional tests asserting status + body for signup conflict (409), token not found (404), token expired/used (422), and cannot-delete-self (409) must pass unchanged — they are the contract lock for item 5.

## Frontend Plan

- No new routes. `login/actions.ts`, `middleware.ts` in both apps are rewritten as adapters (see Item 1); `lib/auth.ts` deleted in both.
- No Server/Client boundary changes: actions stay `'use server'` files; dialogs stay `'use client'` (they already are — state + handlers).
- No form, loading, error, or i18n changes.
- New Vitest coverage in `packages/auth-server-ts` and `apps/admin` (see Integration Coverage). `make test-web` and the CI JS-tests job gain `pnpm -C packages/auth-server-ts test`.

## Phasing

All phases land on this branch; one PR. Each phase ends with `make lint && make test` green and includes its doc sync (`/sync-context-docs` for touched contexts / package AGENTS.md files).

| Phase | Item | Goal | Deliverable |
|-------|------|------|-------------|
| 0 | — | Spec + ADR committed | `.ai/specs/2026-07-02-architecture-deepening.md`, `docs/adr/0001-*.md` |
| 1 | 5 | Exception→HTTP mapping consolidated | `ExceptionStatusMapProvider` + `UserExceptionStatusMap`; catch blocks deleted; functional tests green unchanged |
| 2 | 4 | Port seams real | `tests/Doubles/*`; Unit tests for aggregates, VOs, 3 use cases; `make test-api` green |
| 3 | 6 | Admin dialogs deduplicated | `components/users/dialogs/*` + tests; both menus thin |
| 4 | 1 | Auth session module | `packages/auth-server-ts` + tests; both apps rewired; `lib/auth.ts` deleted; inline baseUrls removed |
| 5 | 3 | OpenAPI seam verified | standalone `gen-api`; committed `openapi.json` + real `types.gen.ts`; `openapi-drift` CI job |
| 6 | 7 | CI single author | `ci.yml` jobs call make targets; `ops/ci/scripts/*` deleted; timing measured |

## Risks & Impact Review

| Risk | Severity | Affected area | Mitigation | Residual |
|------|----------|---------------|------------|----------|
| Error response bodies drift when catch blocks move to the listener | High | API error contract | Copy `code`/`message` strings verbatim; existing functional tests assert status + body and gate the phase | Low |
| `nelmio:apidoc:dump` needs a DB connection after all | Medium | Phase 5 | Fall back to `up-test`-backed `gen-api`; CI job installs runner-native either way | Low |
| Next.js rejects factory-created exports from `'use server'` files | Medium | Phase 4 | Known-good pattern; fallback is a 3-line explicit `async function` wrapper delegating to the factory product | Low |
| Web sign-in persists cookies after `me()` instead of before — behaviour delta if `me()` throws | Low | apps/web login | Factory treats `me()` failure as non-fatal when no `authorize` configured (persists + redirects, as today) | Negligible |
| Docker-in-runner makes CI slower | Medium | Phase 6 | Measure on PR; 2× threshold with documented runner-native fallback for the functional job only | Medium (accepted) |
| Committed `openapi.json` churns in every API PR | Low | DX | That is the mechanism working; `make gen-api` is standalone and fast | Accepted |
| In-memory fakes drift from Doctrine adapter semantics (soft-delete filter) | Medium | Unit-test fidelity | Fakes replicate the documented query invariants; functional tests still cover the real adapter | Low |

Undo: every phase is a pure refactor revertible by `git revert` of its commits; no migrations, no data changes, no contract changes.

## Integration Coverage

| Test ID | Type | Path | Asserts |
|---------|------|------|---------|
| TC-01 | PHPUnit Unit | `apps/api/tests/Unit/User/Domain/UserTest.php` | register emits `user.account.created`; changePassword; softDelete/restore; role invariants |
| TC-02 | PHPUnit Unit | `apps/api/tests/Unit/User/Domain/PasswordRecoveryTokenTest.php` | validate happy path; expired; already-used |
| TC-03 | PHPUnit Unit | `apps/api/tests/Unit/User/Domain/ValueObject/*Test.php` | `Email`/`PlainPassword`/`Role`/`UserId` reject invalid input at construction |
| TC-04 | PHPUnit Unit | `apps/api/tests/Unit/User/Application/SignUpUseCaseTest.php` | creates user via fakes; duplicate email throws `UserAlreadyExists` |
| TC-05 | PHPUnit Unit | `apps/api/tests/Unit/User/Application/ResetPasswordWithTokenUseCaseTest.php` | happy path; each token failure; refresh tokens revoked (spy) |
| TC-06 | PHPUnit Functional | existing `apps/api/tests/Functional/User/**` | unchanged — status + body identical after Phase 1 (contract lock) |
| TC-07 | Vitest | `packages/auth-server-ts/src/__tests__/signIn.test.ts` | bad credentials → error, no cookies; authorize reject → error, **no cookies persisted**; success persists then redirects; `mustResetPassword` redirect rule |
| TC-08 | Vitest | `packages/auth-server-ts/src/__tests__/middleware.test.ts` | public paths/prefixes pass; missing cookies redirect to login with `next` param |
| TC-09 | Vitest + RTL | `apps/admin/src/components/users/dialogs/__tests__/*.test.tsx` | each dialog renders and confirm invokes its action |
| TC-10 | CI | `.github/workflows/ci.yml` `openapi-drift` job | regenerated `openapi.json` + `types.gen.ts` produce no diff |

## Backward Compatibility

- [x] No removed/renamed event IDs
- [x] No removed/renamed API routes
- [x] No removed response fields
- [x] No removed DB columns
- [x] Deprecation bridge added if any contract surface changed — n/a, no contract surface changes

## Final Compliance Report

| Gate | Verdict |
|------|---------|
| Boundary | PASS — Shared never imports User; the map provider inverts the dependency (User → Shared interface). |
| Bus | PASS — controllers keep dispatching through CommandBus/QueryBus; item 5 only removes catch blocks. |
| Mapping | PASS — no domain entity gains ORM attributes; no persistence changes at all. |
| Validation | PASS — value-object construction untouched; TC-03 adds explicit coverage. |
| Idempotency | PASS — no subscribers/workers added or changed. |
| Auth | PASS — no endpoint auth changes; admin sign-in invariant (no cookies before authorize) is preserved and now unit-tested (TC-07). |
| Naming | PASS — no new aggregates/commands/events/tables. |
| DateTime | PASS — `FixedClock` returns `DateTimeImmutable`; no domain code changes. |
| Final readonly | PASS — new PHP classes (`UserExceptionStatusMap`, fakes) follow `final` (+ `readonly` where stateless). |
| strict_types | PASS — all new PHP files declare it. |
| Tests | PASS — TC-01…TC-10 across PHPUnit Unit/Functional, Vitest, and a CI gate. |
| BC | PASS — no contract surface removed or renamed. |

No new business rules introduced — `.ai/business-rules.md` unchanged.

## Changelog

| Date | Change |
|------|--------|
| 2026-07-02 | Spec skeleton drafted; open questions pending. |
| 2026-07-02 | Q1–Q6 answered (all recommendations accepted); full design, phasing, risks, coverage, compliance gate completed. |
