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

CLI: `app:user:promote-admin <email>` grants `ROLE_ADMIN`.

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

Application/
├── Command/SignUp/{SignUpCommand,SignUpCommandHandler,SignUpUseCase}.php
├── Command/SelfResetPassword/{...Command,...CommandHandler,...UseCase}.php
├── Command/ForcePasswordReset/{...Command,...CommandHandler,...UseCase}.php
├── Command/RequestPasswordRecovery/{...Command,...CommandHandler,...UseCase}.php
├── Command/ResetPasswordWithToken/{...Command,...CommandHandler,...UseCase}.php   (wraps body in TransactionInterface for PESSIMISTIC_WRITE)
├── Command/PromoteToAdmin/{PromoteToAdminCommand,PromoteToAdminCommandHandler}.php
└── Query/GetCurrentUser/{GetCurrentUserQuery,GetCurrentUserQueryHandler,CurrentUserResponse}.php

Infrastructure/
├── Persistence/DoctrineUserRepository.php
├── Persistence/DoctrinePasswordRecoveryTokenRepository.php
├── Persistence/Doctrine/{UserModel,PasswordRecoveryTokenModel}.php
├── Symfony/SymfonyPasswordHasher.php
├── Symfony/Security/{SecurityUser,UserProvider}.php
├── Symfony/Console/PromoteAdminCommand.php
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

- `tests/Functional/User/Presentation/Http/SignUp/` — sign-up cases
- `tests/Functional/User/Presentation/Http/RequestPasswordRecovery/` — 4 cases
- `tests/Functional/User/Presentation/Http/ResetPasswordWithToken/` — 6 cases incl. `ItRevokesAllRefreshTokensAfterResetTest`
- `tests/Functional/Support/Fixture/PasswordRecoveryTokenFixture.php` (`issueFor`, `issueExpiredFor`, `issueUsedFor`, `countForUser`)

Add a `<Verb>CommandHandlerTest.php` whenever you add a new Application command.

## Validation Commands

```bash
make test-api ARG="--testsuite Functional --filter User"
```
