# First-boot dev admin seeder

## TLDR

On the first dev boot the stack auto-creates a known admin — `admin@example.com` / `!pw4template` — so a fresh clone can sign into the admin panel immediately without a manual signup + promote. Implemented as an idempotent `app:user:seed-admin` console command (create-or-promote), guarded to non-prod environments, invoked from `apps/api/bin/start` only when `APP_ENV=dev`. Documented in the README quickstart and getting-started.

## Overview

Today a fresh clone can reach `admin.localhost` only after signing up a user via the API/web app and then running `make seed-admin EMAIL=…` to promote it. That's three manual steps before the admin panel is usable. A template should boot ready-to-explore. This adds a first-boot seed of a **well-known dev admin**, with a loud "dev-only, change before prod" caveat.

## Problem Statement

- No admin exists on first boot; `getting-started.md §6` documents a manual signup-then-promote dance.
- The `seed-admin` make target only *promotes* an existing user — it cannot create one with a password, so there's no single command that yields a login-ready admin from an empty database.

## Proposed Solution

A new idempotent domain operation "ensure an admin exists" (create with `[ROLE_USER, ROLE_ADMIN]` if absent, promote if present), exposed as a console command with dev-only guard and template-default credentials, wired into first boot.

| Piece | Location |
|-------|----------|
| CQRS command + handler + use case | `apps/api/src/User/Application/Command/EnsureAdmin/` |
| Console command `app:user:seed-admin` | `apps/api/src/User/Infrastructure/Symfony/Console/SeedAdminCommand.php` |
| First-boot hook (dev-only) | `apps/api/bin/start` |
| Docs | `README.md` quickstart, `docs/getting-started.md §6` |

## Architecture

- **Bounded context**: User (Application + Infrastructure + Presentation-adjacent console). No other context touched.
- **Bus**: the console command dispatches `EnsureAdminCommand` on the **command bus** (never calls the handler directly — ADR/convention). Mirrors the existing `PromoteAdminCommand → PromoteToAdminCommand` pair.
- **New aggregates / VOs**: none. Reuses `User`, `Email`, `PlainPassword`, `Role`.
- **Events**: creating a new admin emits `user.account.registered` via the event bus (same as `SignUpUseCase`); the promote-existing path emits nothing (matches `PromoteToAdminUseCase`).

### `EnsureAdminUseCase` (idempotent, create-or-promote)

```
findByEmail(email)
  ├─ exists → user.promoteToAdmin(); save            (idempotent; no event)
  └─ absent → User::register(random id, email, hash(password), [USER, ADMIN], clock.now());
               save; eventBus.publish(...pullDomainEvents())
```

Deps: `UserRepository`, `PasswordHasherInterface`, `EventBus`, `ClockInterface` — same set `SignUpUseCase` already uses.

### `SeedAdminCommand` console (dev-only guard)

- Name `app:user:seed-admin`; optional args `email` (default `admin@example.com`) and `password` (default `!pw4template`).
- Constructor `(CommandBus $bus, #[Autowire('%kernel.environment%')] string $environment)`.
- If `environment === 'prod'`: print an error ("refuses to run in prod") and return `FAILURE` **without dispatching** — a known-password admin must never be seeded into production.
- Otherwise dispatch `EnsureAdminCommand(email, password)` and return `SUCCESS`.

### First-boot wiring (`apps/api/bin/start`)

After migrations, dev only:

```sh
if [ "${APP_ENV:-prod}" = "dev" ]; then
  php bin/console app:user:seed-admin || true   # idempotent; never blocks boot
fi
```

Idempotent, so it's safe on every dev boot, not just the literal first one.

## Security / Risk

| Risk | Severity | Mitigation |
|------|----------|------------|
| Known-password admin reaches production | High | Console command hard-refuses `prod`; boot hook is `APP_ENV=dev`-gated; docs shout "dev-only, change before prod". Two independent guards. |
| Seed failure blocks API boot | Low | `|| true` on the boot hook; command is idempotent |
| Password too weak for `PlainPassword` VO | Low | `!pw4template` is 12 chars, satisfies the ≥8 rule |

## Backward Compatibility

- [x] No removed/renamed event IDs, routes, response fields, DB columns
- [x] Additive only: new command + new boot line; existing `make seed-admin` (promote) untouched

## Integration Coverage

| Test ID | Type | Path | Asserts |
|---------|------|------|---------|
| TC-01 | PHPUnit Functional | `tests/Functional/User/Infrastructure/Console/SeedAdminCommandTest.php` | creates+promotes when missing; idempotent on re-run; promotes an existing non-admin |
| TC-02 | PHPUnit Unit | `tests/Unit/User/Infrastructure/Console/SeedAdminCommandProdGuardTest.php` | refuses in `prod`, returns FAILURE, dispatches nothing |
| TC-03 | PHPUnit Unit | `tests/Unit/User/Application/EnsureAdminUseCaseTest.php` | create path emits `user.account.registered` with both roles; promote path adds ROLE_ADMIN and emits nothing |

## Final Compliance Report

| Gate | Verdict |
|------|---------|
| Boundary | PASS — User context only; no cross-context import |
| Bus | PASS — console dispatches via CommandBus; handler never called directly |
| Mapping | PASS — no persistence/ORM changes |
| Validation | PASS — `Email`/`PlainPassword` validate at construction |
| Idempotency | PASS — create-or-promote is safe on repeat; boot hook runs every dev boot |
| Auth | PASS — the seeded admin has `ROLE_ADMIN`; prod guarded |
| Naming | PASS — `EnsureAdminCommand` (CQRS) / `SeedAdminCommand` (console) mirror the promote pair |
| DateTime | PASS — `ClockInterface` (`DateTimeImmutable`) |
| Final readonly | PASS — command DTO `final readonly` |
| strict_types | PASS — all new files |
| Tests | PASS — TC-01…TC-03 |
| BC | PASS — additive |

## Changelog

| Date | Change |
|------|--------|
| 2026-07-03 | Spec drafted. |
| 2026-07-03 | Implemented — `EnsureAdmin` command/handler/use case, `SeedAdminCommand` console (dev-guarded), first-boot hook in `bin/start`, docs (README + getting-started §6 + User AGENTS.md). TC-01/02/03 green (7 tests). |
