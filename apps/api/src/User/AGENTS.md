# User — Bounded Context

Owns: authentication, account lifecycle, password hashing, role assignment, password recovery.

## Surface

| Endpoint | Method | Auth | Notes |
|----------|--------|------|-------|
| `/auth/signup` | POST | public | Creates a User with `ROLE_USER`. |
| `/auth/login` | POST | public | Lexik JWT `json_login`. Returns `token` + `refresh_token`. |
| `/auth/refresh` | POST | public | Gesdinet refresh-token rotation. |
| `/auth/forgot-password` | POST | public | Issues a 1-hour recovery token, emails it. Always 204 (BR-U05). Rate-limited 3/10 min per IP. |
| `/auth/reset-password` | POST | public | Redeems the recovery token to set a new password. Marks token used; revokes all of the user's refresh tokens. Rate-limited 10/min per IP. |
| `/api/users/me/reset-password` | POST | `IS_AUTHENTICATED_FULLY` | Authenticated self-reset (existing). |
| `/api/admin/users/{id}/force-password-reset` | POST | `ROLE_ADMIN` | Admin-forced reset (existing). |
| `/api/me` | GET | `IS_AUTHENTICATED_FULLY` | Current user payload. |
| `/api/admin/users` | GET | `ROLE_ADMIN` | Paginated list of every user. |

CLI:
- `app:user:promote-admin <email>` grants `ROLE_ADMIN` (user must exist).
- `app:user:seed-admin [email] [password]` create-or-promote an admin, idempotent; defaults `admin@example.com` / `!pw4template`. **Dev/test only — refuses to run in `prod`.** Invoked on first dev boot from `apps/api/bin/start`.

### Password recovery — flow at a glance

1. `POST /auth/forgot-password { email }` → issues a `PasswordRecoveryToken` (`bin2hex(random_bytes(48))`, SHA-256 hashed at rest, 1-hour TTL). Supersedes any prior unused tokens for the same user. Sends an email with the plain token in the link via `PasswordRecoveryEmailSender`.
2. `POST /auth/reset-password { token, newPassword }` → looks up by `SHA-256(token)` with `PESSIMISTIC_WRITE` lock inside a transaction, validates (`PasswordRecoveryToken::validate`), changes the password, marks the token used, **and revokes all Gesdinet refresh tokens for the user** (`RefreshTokenRevoker`).

The Symfony Mailer DSN comes from `MAILER_DSN`. In dev this defaults to `smtp://mailpit:1025` (Mailpit dev service). **Production forks must override `MAILER_DSN` and `MAILER_FROM`** — the local default points at a dev-only service and will silently fail (logged, user still sees 204) without it.

## Always

- Use `Email`, `PlainPassword`, `HashedPassword`, `UserId` value objects at the boundary.
- Pass passwords through `PasswordHasherInterface`. NEVER hash inline.
- Emit `UserRegistered` after sign-up.
- Enforce refresh-token single-use rotation (Gesdinet config: `single_use: true`).
- After any password change reachable without authentication (currently only `ResetPasswordWithToken`), revoke all of the user's refresh tokens via `RefreshTokenRevoker` — a stolen `rt` must not survive a recovery.
- Update the `users`, `refresh_tokens`, and `password_recovery_tokens` tables only through migrations.

## Never

- Never import another context's `Domain/`/`Application/` (e.g. `App\<OtherContext>\Domain\…`). Communicate via events.
- Never catch domain exceptions in controllers — context-specific statuses live in `Presentation/Http/UserExceptionStatusMap.php` (token failures: 404 not-found / 422 expired / 422 already-used, with fixed messages); everything else falls back to the Shared `ExceptionListener` (`DomainException`→409 `CONFLICT`).
- Never store plaintext passwords. The aggregate only knows `HashedPassword`.
- Never log `PlainPassword` or any password-shaped string.
- Never return the password hash from any endpoint.

## Structure

```
Domain/
├── User.php                              (aggregate)
├── UserId.php                            (Uuid VO)
├── Email.php                             (string VO with normalisation + RFC validation)
├── PlainPassword.php                     (DTO; length checks)
├── HashedPassword.php                    (string VO; opaque)
├── Role.php                              (enum: USER, ADMIN)
├── PasswordHasherInterface.php           (port)
├── UserRepository.php                    (port)
├── PasswordRecoveryToken.php             (aggregate; bin2hex(random_bytes(48)), SHA-256 stored)
├── PasswordRecoveryTokenId.php           (Uuid VO)
├── PasswordRecoveryTokenRepository.php   (port — findByTokenHashForUpdate + markAllUnusedAsUsed)
├── PasswordRecoveryEmailSender.php       (port — concrete in Infrastructure/Mail)
├── RefreshTokenRevoker.php               (port — concrete in Infrastructure/Security)
├── Event/UserRegistered.php
└── Exception/{UserNotFound,UserAlreadyExists,PasswordRecoveryToken{NotFound,Expired,AlreadyUsed}}.php

Application/                 (grouped by use case, not by trigger — no Command/ or Query/ folder)
├── SignUp/{SignUpCommand,SignUpCommandHandler,SignUpUseCase}.php
├── SelfResetPassword/{...Command,...CommandHandler,...UseCase}.php
├── ForcePasswordReset/{...Command,...CommandHandler,...UseCase}.php
├── RequestPasswordRecovery/{...Command,...CommandHandler,...UseCase}.php
├── ResetPasswordWithToken/{...Command,...CommandHandler,...UseCase}.php   (wraps body in TransactionInterface for PESSIMISTIC_WRITE)
├── AdminCreateUser/, PromoteToAdmin/, UpdateUserRoles/, SoftDeleteUser/, RestoreUser/   ({...Command,...CommandHandler,...UseCase}.php each)
├── EnsureAdmin/{EnsureAdminCommand,EnsureAdminCommandHandler,EnsureAdminUseCase}.php   (idempotent create-or-promote; backs the dev seeder)
├── GetCurrentUser/{GetCurrentUserQuery,GetCurrentUserQueryHandler,GetCurrentUserUseCase,CurrentUserResponse}.php
├── GetUserById/{GetUserByIdQuery,GetUserByIdQueryHandler,GetUserByIdUseCase,UserDetailResponse}.php
└── ListUsers/{ListUsersQuery,ListUsersQueryHandler,ListUsersUseCase,UserListResponse,UserSummary}.php
```

Every action folder is `Application/<Action>/` holding one `<Action>UseCase` plus its
trigger(s). A command handler, a query handler, and a `DomainEventSubscriber` can all live
in one folder, each delegating to that use case — so a future subscriber that reacts to
another context's event has a natural home. See `docs/domain-events.md`.
```

Infrastructure/
├── Persistence/DoctrineUserRepository.php
├── Persistence/DoctrinePasswordRecoveryTokenRepository.php
├── Persistence/Doctrine/{UserModel,PasswordRecoveryTokenModel}.php
├── Symfony/SymfonyPasswordHasher.php
├── Symfony/Security/{SecurityUser,UserProvider}.php
├── Symfony/Console/PromoteAdminCommand.php    (app:user:promote-admin)
├── Symfony/Console/SeedAdminCommand.php        (app:user:seed-admin — dev-only, dispatches EnsureAdminCommand)
├── Security/{RefreshToken,GesdinetRefreshTokenRevoker}.php
└── Mail/SymfonyPasswordRecoveryEmailSender.php   (catches TransportException, logs $e->getMessage())

Presentation/
└── Http/{SignUpController, MeController, UserSelfResetPasswordController,
        ForgotPasswordController, ResetPasswordWithTokenController,
        UserExceptionStatusMap,   (ExceptionStatusMapProvider — token-failure statuses)
        Dto/{SignUpRequest, SelfResetPasswordRequest, ForgotPasswordRequest, ResetPasswordWithTokenRequest}, ...}.php
```

Login + Refresh endpoints come from Symfony Security + Lexik + Gesdinet bundles — no controller needed.

### Atomic redemption

`password_recovery_tokens` has a partial unique index `(user_id) WHERE used_at IS NULL` so the
DB enforces "at most one active token per user" — concurrent reset requests race-fail at INSERT
time. The redemption path also takes `PESSIMISTIC_WRITE` on the row inside a transaction.

### Rate limiting

Two rate limiters configured in `config/packages/rate_limiter.yaml`:

- `forgot_password`: 3 / 10 min per client IP (mitigates email enumeration / spam)
- `reset_password`: 10 / min per client IP (mitigates Argon2 CPU-DoS)

In tests both are bumped to 1000/min via a `when@test:` block, and the cache pool is swapped to
the in-memory array adapter.

`config/packages/framework.yaml` sets `trusted_proxies` + `trusted_headers` so the limiter sees
the real client IP — without it, every request looks like it came from Traefik+nginx and the
limit becomes effectively global.

## Tests

- `tests/Unit/User/Domain/` — aggregate + value-object unit tests (no framework, no DB; run in ms)
- `tests/Unit/User/Application/` — use-case tests through the port interfaces, using the fakes in `tests/Doubles/` (`InMemoryUserRepository`, `InMemoryPasswordRecoveryTokenRepository`, `FakePasswordHasher`, `SpyEventBus`, `SpyRefreshTokenRevoker`, `SpyPasswordRecoveryEmailSender`, `NullTransaction`) plus the shared kernel's `FrozenClock`
- `tests/Functional/User/Presentation/Http/SignUp/` — sign-up cases (`BaseSignUpTest` + `It*Test`)
- `tests/Functional/User/Presentation/Http/RequestPasswordRecovery/` — 4 cases
- `tests/Functional/User/Presentation/Http/ResetPasswordWithToken/` — 6 cases incl. `ItRevokesAllRefreshTokensAfterResetTest`
- `tests/Functional/User/Infrastructure/Console/SeedAdmin/` — admin-seeder cases (`BaseSeedAdminTest` + `It*Test`)
- `tests/Support/Fixtures/PasswordRecoveryTokenFixture.php` (`issueFor`, `issueExpiredFor`, `issueUsedFor`, `countForUser`); HTTP via `tests/Support/Pages/UserPage.php`

Each functional scenario is one `It<Scenario>Test` extending a `Base<UseCase>Test`, in AAA form (`arrange/act/assert`). Add a new scenario class per behaviour.

## Validation Commands

```bash
make test-api ARG="--testsuite Functional --filter User"
```
