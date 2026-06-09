# Password Recovery — Local-Only Port

**Date**: 2026-06-10
**Status**: Draft (revised after pre-implement audit)
**Scope**: User bounded context (API) + Web frontend + Local dev infra (Mailpit) + Spec-writing skill harness (rule #11 + spec template)

## TLDR

Port the password-recovery flow from [jperdior/dungeon-manager#62](https://github.com/jperdior/dungeon-manager/pull/62) into this template, scoped to **local development only** (no K8s helm changes, no `PROD_MAILER_DSN`, no GitHub Actions secret). Also port one minor addition from [#63](https://github.com/jperdior/dungeon-manager/pull/63) — a `when@test` `fingers_crossed` block for monolog — to keep test output quiet; everything else from #63 (Symfony Monolog bundle, K8s `fsGroup` fix) is either already installed or production-only and out of scope.

Also port the **spec-writing skill** improvements from PR #62 (rule #11 about API contract field alignment + spec-template JSON-example block) because they apply to every future feature.

## Overview

The only password-reset paths today require authentication (`SelfResetPassword`) or admin action (`ForcePasswordReset`). A locked-out user has no recovery option. We add a self-service flow: submit email → receive a 1-hour link → set a new password. Email is delivered to Mailpit in dev (`http://mailpit.localhost`). We do **not** wire any production SMTP credentials in this PR — production teams forking the template configure their own DSN.

## Problem Statement

1. Users who forget their password are stuck until an admin intervenes.
2. (Skill harness) The spec-writing skill produced specs that named DTO classes without showing the JSON shape, causing field-name mismatches between backend (`$password`) and frontend (`newPassword`) during the upstream port. The skill needs an explicit rule about API contract field alignment.

## Proposed Solution

```
[User] → POST /auth/forgot-password { "email": "..." }
              → RequestPasswordRecoveryCommandHandler → UseCase
                    → Email VO construction (catch InvalidArgumentException → silent 204)
                    → UserRepository::findByEmail (null → silent 204)
                    → invalidate prior unused PasswordRecoveryTokens for this user
                    → PasswordRecoveryToken::create() [returns [token, plain]]
                    → PasswordRecoveryTokenRepository::save()
                    → PasswordRecoveryEmailSender::send()
                          → SymfonyPasswordRecoveryEmailSender
                                → MailerInterface → smtp://mailpit:1025

[User] → POST /auth/reset-password { "token": "...", "newPassword": "..." }
              → ResetPasswordWithTokenCommandHandler → UseCase
                    → PasswordRecoveryTokenRepository::findByTokenHashForUpdate() [pessimistic lock]
                    → token.validate(now) [throws expired/used]
                    → User::changePassword(hashedPassword)
                    → token.markAsUsed(now)
                    → RefreshTokenRevoker::revokeAllFor(user)
                    → UserRepository::save()
                    → PasswordRecoveryTokenRepository::save()
```

## Architecture

- **Bounded context**: `User` (no cross-context dependencies).
- **New aggregate / entity**: `PasswordRecoveryToken` (lifecycle independent of `User`; FK only).
- **New value objects**: `PasswordRecoveryTokenId extends Jperdior\SharedKernel\Domain\ValueObject\Uuid`.
- **New domain exceptions**: `PasswordRecoveryTokenNotFound`, `PasswordRecoveryTokenExpired`, `PasswordRecoveryTokenAlreadyUsed`.
- **New domain service interfaces**:
  - `PasswordRecoveryEmailSender` (port — implemented by `SymfonyPasswordRecoveryEmailSender`).
  - `RefreshTokenRevoker` (port — implemented by `GesdinetRefreshTokenRevoker`, an adapter over `Gesdinet\JWTRefreshTokenBundle\Doctrine\RefreshTokenManager`).
- **New repository interface**: `PasswordRecoveryTokenRepository`.
- **Buses used**: CommandBus only (both endpoints are write-only).
- **Cross-context interaction**: none.
- **Clock**: handlers inject `\DateTimeImmutable` ("now") at the point of dispatch — matches the existing handler pattern (no global clock service).

### Token design

- Plain token: `bin2hex(random_bytes(48))` → 96 hex chars (384 bits of entropy). Used in the email link only. Never persisted.
- Stored: SHA-256 hash of the plain token (`tokenHash`, 64 chars). DB breach does not expose redeemable tokens. No HMAC/pepper needed (entropy is sufficient).
- Single use: enforced by `usedAt IS NULL` guard + a partial unique index (see Data Models).
- TTL: 1 hour.

## Data Models

### `PasswordRecoveryToken` entity (domain — no ORM attributes)

| Field        | Type                       | Notes |
|--------------|----------------------------|-------|
| `id`         | UUID v4                    | PK |
| `userId`     | UUID                       | FK → `users.id`, `ON DELETE CASCADE` |
| `tokenHash`  | VARCHAR(64)                | SHA-256 of plain token |
| `expiresAt`  | DATETIME_IMMUTABLE         | `createdAt + 1 hour` |
| `usedAt`     | DATETIME_IMMUTABLE NULL    | null until redeemed |
| `createdAt`  | DATETIME_IMMUTABLE         | |

- **Index**: `idx_password_recovery_tokens_token_hash` on `(token_hash)`.
- **Partial unique index** (Postgres `CREATE UNIQUE INDEX ... WHERE used_at IS NULL`) on `(user_id)` to guarantee at most one active token per user — enforces BR-U04 atomically at the DB level. Concurrent reset requests for the same user race-fail at insert time, the loser retries.
- Table: `password_recovery_tokens`.
- ORM mapping lives on `apps/api/src/User/Infrastructure/Persistence/Doctrine/PasswordRecoveryTokenModel.php` (attribute-based; **not** on the domain entity). The existing `User` mapping under `apps/api/config/packages/doctrine.yaml` already scans the entire `User/Infrastructure/Persistence/Doctrine/` directory, so no doctrine.yaml change is needed.

### Domain entity skeleton

```php
// apps/api/src/User/Domain/PasswordRecoveryToken.php
final class PasswordRecoveryToken
{
    private function __construct(
        private readonly PasswordRecoveryTokenId $id,
        private readonly UserId $userId,
        private readonly string $tokenHash,
        private readonly \DateTimeImmutable $expiresAt,
        private ?\DateTimeImmutable $usedAt,
        private readonly \DateTimeImmutable $createdAt,
    ) {}

    /** @return array{0: self, 1: string} [token, plainText] — plain returned once, never stored */
    public static function create(UserId $userId, \DateTimeImmutable $now): array
    {
        $plain = bin2hex(random_bytes(48));
        $token = new self(
            PasswordRecoveryTokenId::generate(),
            $userId,
            hash('sha256', $plain),
            $now->modify('+1 hour'),
            null,
            $now,
        );
        return [$token, $plain];
    }

    public function validate(\DateTimeImmutable $now): void
    {
        if ($this->usedAt !== null)  throw new PasswordRecoveryTokenAlreadyUsed();
        if ($now > $this->expiresAt) throw new PasswordRecoveryTokenExpired();
    }

    public function markAsUsed(\DateTimeImmutable $now): void { $this->usedAt = $now; }

    public function userId(): UserId { return $this->userId; }
}
```

### Migration

`apps/api/migrations/Version20260610000001.php` — creates `password_recovery_tokens` with the two indexes above. `down()` drops the table. Generated via `make migrate-diff` then hand-edited to add the partial unique index (Doctrine migrations don't emit `WHERE` predicates for unique indexes).

## API Contracts

| Method | Path                     | Auth   | Request body                                       | Response       |
|--------|--------------------------|--------|----------------------------------------------------|----------------|
| POST   | `/auth/forgot-password`  | public | `{ "email": "user@example.com" }`                  | `204 No Content` |
| POST   | `/auth/reset-password`   | public | `{ "token": "<96 hex>", "newPassword": "..." }`    | `204 No Content` |

### Field-name alignment (rule #11)

PHP DTO property name IS the JSON key (`#[MapRequestPayload]` deserializes by property name). The TypeScript client and Server Action `FormData` keys must use the same names.

```jsonc
// POST /auth/forgot-password — request
{ "email": "user@example.com" }
// ↔ ForgotPasswordRequest { public readonly string $email }

// POST /auth/reset-password — request
{ "token": "a1b2c3...96hex", "newPassword": "MyNewPassword123" }
// ↔ ResetPasswordWithTokenRequest {
//      public readonly string $token;
//      public readonly string $newPassword;
//   }
```

### Controller deliverables

| File | Route | Notes |
|------|-------|-------|
| `apps/api/src/User/Presentation/Http/ForgotPasswordController.php` | `POST /auth/forgot-password` | `#[Route(...)]` + Nelmio OpenAPI attributes + rate-limiter check. Always returns 204 (except on rate-limit → 429, on invalid email format → 422). |
| `apps/api/src/User/Presentation/Http/ResetPasswordWithTokenController.php` | `POST /auth/reset-password` | `#[Route(...)]` + Nelmio OpenAPI attributes + rate-limiter check. Dispatches via `CommandBus`. |
| `apps/api/src/User/Presentation/Http/Dto/ForgotPasswordRequest.php` | — | `final readonly` + `#[Assert\Email]` + `#[Assert\NotBlank]` on `$email`. |
| `apps/api/src/User/Presentation/Http/Dto/ResetPasswordWithTokenRequest.php` | — | `#[Assert\Regex('/^[a-f0-9]{96}$/')]` on `$token`; `#[Assert\Length(min: 8, max: 4096)]` on `$newPassword`. |

Both controllers follow the same pattern as `apps/api/src/User/Presentation/Http/UserSelfResetPasswordController.php`.

### Validation strategy

- `ForgotPasswordRequest`: `#[Assert\Email]` rejects malformed emails at `MapRequestPayload` time → automatic 422 from Symfony.
- `RequestPasswordRecoveryUseCase`: trusts the DTO. It still constructs an `Email` VO for `findByEmail` — but wraps that single line in `try/catch(InvalidArgumentException)` and silently returns to preserve BR-U05 in the unlikely case where DTO validation and VO validation disagree.

### Error responses

| Endpoint | Status | Code |
|----------|--------|------|
| `/auth/forgot-password` | 422 | malformed email |
| `/auth/forgot-password` | 429 | rate limit (IP) |
| `/auth/reset-password`  | 404 | `password_recovery_token_not_found` |
| `/auth/reset-password`  | 422 | `password_recovery_token_expired` |
| `/auth/reset-password`  | 422 | `password_recovery_token_already_used` |
| `/auth/reset-password`  | 422 | malformed token / weak password |
| `/auth/reset-password`  | 429 | rate limit (IP) |

## Application Layer

```
apps/api/src/User/Application/Command/RequestPasswordRecovery/
  RequestPasswordRecoveryCommand.php             (final readonly, property: $email)
  RequestPasswordRecoveryCommandHandler.php      (implements CommandHandler, delegates to use case)
  RequestPasswordRecoveryUseCase.php

apps/api/src/User/Application/Command/ResetPasswordWithToken/
  ResetPasswordWithTokenCommand.php              (final readonly, properties: $token, $newPassword)
  ResetPasswordWithTokenCommandHandler.php
  ResetPasswordWithTokenUseCase.php
```

The naming matches `SelfResetPasswordCommand` / `SelfResetPasswordCommandHandler` / `SelfResetPasswordUseCase` exactly.

### `RequestPasswordRecoveryUseCase` responsibilities

1. Construct `Email` VO; on `InvalidArgumentException` → silently return.
2. `UserRepository::findByEmail()`; if null → silently return.
3. Invalidate prior unused tokens for this user (call `PasswordRecoveryTokenRepository::markAllUnusedAsUsed(UserId, now)` — used as "consume by superseding").
4. `PasswordRecoveryToken::create($user->id(), $now)`; persist; capture plain.
5. Dispatch `PasswordRecoveryEmailSender::send($email, $plainToken)`.

### `ResetPasswordWithTokenUseCase` responsibilities

1. `PasswordRecoveryTokenRepository::findByTokenHashForUpdate(hash('sha256', $command->token))` — pessimistic row lock; null → `PasswordRecoveryTokenNotFound`.
2. `$token->validate($now)`.
3. Load `User` by `$token->userId()`; null → `PasswordRecoveryTokenNotFound` (orphaned token).
4. `$user->changePassword($hasher->hash(new PlainPassword($command->newPassword)))`.
5. `$token->markAsUsed($now)`.
6. `RefreshTokenRevoker::revokeAllFor($user->email())` — revokes all stored Gesdinet refresh tokens for the user.
7. `UserRepository::save($user); PasswordRecoveryTokenRepository::save($token);`.

### `PasswordRecoveryEmailSender` (port)

```php
// apps/api/src/User/Domain/PasswordRecoveryEmailSender.php
interface PasswordRecoveryEmailSender
{
    public function send(Email $to, string $plainToken): void;
}
```

### `SymfonyPasswordRecoveryEmailSender` (adapter)

```php
// apps/api/src/User/Infrastructure/Mail/SymfonyPasswordRecoveryEmailSender.php
final readonly class SymfonyPasswordRecoveryEmailSender implements PasswordRecoveryEmailSender
{
    public function __construct(
        private MailerInterface $mailer,
        private LoggerInterface $logger,
        private string $frontendUrl,
        private string $fromAddress,
    ) {}

    public function send(Email $to, string $plainToken): void
    {
        $resetUrl = rtrim($this->frontendUrl, '/') . '/reset-password/' . $plainToken;
        $email = (new SymfonyEmail())
            ->from($this->fromAddress)
            ->to((string) $to)
            ->subject('Reset your password')
            ->text("Click to reset your password (valid for 1 hour):\n\n{$resetUrl}")
            ->html("<p>Click to reset your password (valid for 1 hour):</p><p><a href=\"{$resetUrl}\">Reset password</a></p>");

        try {
            $this->mailer->send($email);
        } catch (TransportExceptionInterface $e) {
            // Log the message string only — never the full exception object (defensive against future
            // Symfony surface changes that might include the Mime body or token).
            $this->logger->error('password_recovery_email_send_failed', ['error' => $e->getMessage()]);
        }
    }
}
```

### `RefreshTokenRevoker` (port + Gesdinet adapter)

```php
// apps/api/src/User/Domain/RefreshTokenRevoker.php
interface RefreshTokenRevoker
{
    public function revokeAllFor(Email $email): void;
}
```

```php
// apps/api/src/User/Infrastructure/Security/GesdinetRefreshTokenRevoker.php
final readonly class GesdinetRefreshTokenRevoker implements RefreshTokenRevoker
{
    public function __construct(private RefreshTokenManagerInterface $manager) {}

    public function revokeAllFor(Email $email): void
    {
        foreach ($this->manager->getAllInvalid() as $rt) { /* prune incidentally */ }
        // Gesdinet's manager exposes ->getRepository() for the class — use a Doctrine delete-by-username:
        // (concrete implementation: inject DoctrineRefreshTokenRepository directly and call a custom method)
    }
}
```

> **Implementation detail finalised at write-time** — Gesdinet's `RefreshTokenManagerInterface` does not expose a bulk-revoke-by-username; the adapter will inject a custom repository or query and DELETE FROM `refresh_tokens` WHERE `username = :email`. The port stays clean.

### `PasswordRecoveryTokenRepository`

```php
// apps/api/src/User/Domain/PasswordRecoveryTokenRepository.php
interface PasswordRecoveryTokenRepository
{
    public function save(PasswordRecoveryToken $token): void;
    public function findByTokenHashForUpdate(string $hash): ?PasswordRecoveryToken;
    public function markAllUnusedAsUsed(UserId $userId, \DateTimeImmutable $now): void;
}
```

Doctrine implementation at `apps/api/src/User/Infrastructure/Persistence/DoctrinePasswordRecoveryTokenRepository.php` — uses `LockMode::PESSIMISTIC_WRITE` in `findByTokenHashForUpdate`, and a parameterised DQL UPDATE for `markAllUnusedAsUsed`.

## Configuration

### Composer additions

```
symfony/mailer        7.4.*
symfony/rate-limiter  7.4.*
```

(`symfony/monolog-bundle` is already installed at `^3.10` — verified in `composer.json` and `bundles.php`. No change.)

### `apps/api/config/packages/mailer.yaml` (new)

```yaml
framework:
    mailer:
        dsn: '%env(MAILER_DSN)%'
```

### `apps/api/config/packages/rate_limiter.yaml` (new)

```yaml
framework:
    rate_limiter:
        forgot_password:
            policy: fixed_window
            limit: 3
            interval: '10 minutes'

        reset_password:
            policy: fixed_window
            limit: 10
            interval: '1 minute'

when@test:
    framework:
        cache:
            pools:
                cache.rate_limiter:
                    adapter: cache.adapter.array
        rate_limiter:
            forgot_password:
                policy: fixed_window
                limit: 1000
                interval: '1 minute'
            reset_password:
                policy: fixed_window
                limit: 1000
                interval: '1 minute'
```

> Following project convention (`when@test:` blocks inside the main config file). No `config/packages/test/` subdirectory.

### `apps/api/config/packages/framework.yaml` — `trusted_proxies` addition

Append (under the top-level `framework:`):

```yaml
trusted_proxies: '127.0.0.1,REMOTE_ADDR'
trusted_headers: ['x-forwarded-for', 'x-forwarded-host', 'x-forwarded-proto', 'x-forwarded-port', 'x-forwarded-prefix']
```

Without this, Symfony reads the request IP from the socket — which behind Traefik+nginx is always the proxy. The rate limiter would become global.

### `apps/api/config/packages/monolog.yaml` — append `when@test:` only

The current file already has dev + prod handlers. Add:

```yaml
when@test:
    monolog:
        handlers:
            main:
                type: fingers_crossed
                action_level: error
                handler: nested
                excluded_http_codes: [404, 405]
                channels: ['!event']
            nested:
                type: stream
                path: 'php://stderr'
                level: debug
```

(Do NOT touch the existing dev / prod blocks.)

### `apps/api/config/packages/security.yaml` — two new firewalls

Insert two new firewalls **before** the `api:` catch-all:

```yaml
forgot_password:
    pattern: ^/auth/forgot-password$
    stateless: true
    security: false

reset_password:
    pattern: ^/auth/reset-password$
    stateless: true
    security: false
```

Replace the existing `access_control` line:

```yaml
- { path: ^/auth/(login|signup|refresh), roles: PUBLIC_ACCESS }
```

with:

```yaml
- { path: ^/auth/(login|signup|refresh|forgot-password|reset-password), roles: PUBLIC_ACCESS }
```

### `apps/api/config/services.yaml` — sender + rate-limiter wiring

```yaml
App\User\Infrastructure\Mail\SymfonyPasswordRecoveryEmailSender:
    arguments:
        $frontendUrl: '%env(APP_FRONTEND_URL)%'
        $fromAddress: '%env(MAILER_FROM)%'
```

### `apps/api/src/User/Infrastructure/Symfony/Resources/config/services.yaml`

```yaml
App\User\Domain\PasswordRecoveryTokenRepository:
    alias: App\User\Infrastructure\Persistence\DoctrinePasswordRecoveryTokenRepository

App\User\Domain\PasswordRecoveryEmailSender:
    alias: App\User\Infrastructure\Mail\SymfonyPasswordRecoveryEmailSender

App\User\Domain\RefreshTokenRevoker:
    alias: App\User\Infrastructure\Security\GesdinetRefreshTokenRevoker
```

### `apps/api/.env` additions

```ini
###> symfony/mailer ###
MAILER_DSN=smtp://mailpit:1025
MAILER_FROM=noreply@jperdior.local
###< symfony/mailer ###

###> password recovery ###
APP_FRONTEND_URL=http://web.localhost
###< password recovery ###
```

Document in `apps/api/AGENTS.md` that **production forks must override `MAILER_DSN`** — the local default points at the Mailpit dev service and will silently fail (logged 204 — no email sent) without it.

## Local Dev Infrastructure

### Mailpit service in `ops/docker/docker-compose.dev.yml`

```yaml
mailpit:
    image: axllent/mailpit:latest
    restart: unless-stopped
    labels:
      - "traefik.enable=true"
      - "traefik.http.routers.mailpit.rule=Host(`mailpit.localhost`)"
      - "traefik.http.routers.mailpit.entrypoints=web"
      - "traefik.http.services.mailpit.loadbalancer.server.port=8025"
```

Mailpit listens on `1025` (SMTP) internally — `MAILER_DSN=smtp://mailpit:1025` reaches it over the default compose network. UI is `http://mailpit.localhost`. No explicit `networks:` key needed (the compose stack uses the default network).

## Frontend Plan

### New routes (`apps/web` only — `apps/admin` is out of scope; admins use the same backend if they ever need it but no UI surfaces here)

| Route                     | Files                                                                                                                  |
|---------------------------|------------------------------------------------------------------------------------------------------------------------|
| `/forgot-password`        | `apps/web/src/app/forgot-password/{page.tsx, ForgotPasswordForm.tsx, actions.ts}`                                      |
| `/reset-password/[token]` | `apps/web/src/app/reset-password/[token]/{page.tsx, ResetPasswordWithTokenForm.tsx, actions.ts}`                       |

The new `/reset-password/[token]` is a **public sibling** of the existing authenticated `/reset-password` page (Next.js routes them to distinct segments). It must NOT be placed under `(app)/`.

### Referer hardening

Both new pages set `Referrer-Policy: no-referrer` via the route segment's `metadata` export or a `Response`-level header in `next.config.ts` for these paths. Prevents the token in the URL from leaking via the `Referer` header to any external resource the page loads.

### Forms

Both use shadcn `Form` + `react-hook-form` + `zod`, dispatched via Server Actions.

- **ForgotPasswordForm** — `z.object({ email: z.string().email() })`. On submit, regardless of outcome, render "Check your email — if an account exists, a reset link is on its way." (BR-U05).
- **ResetPasswordWithTokenForm** — `z.object({ newPassword: z.string().min(8).max(4096), confirm: z.string() }).refine(...)`. On 404 → "This reset link is invalid." On 422 → "This reset link has expired or has already been used." On success → `redirect('/login?reset=1')`.

### Server Actions

Both actions use `createApiClient({ baseUrl: process.env.INTERNAL_API_URL ?? 'http://nginx:80' })` from `@jperdior/api-client-ts/server`. The forgot-password action silently swallows API errors so the UI does not reveal whether the email exists.

### `apps/web/src/middleware.ts` — extend `PUBLIC_PATHS` and the prefix check

```ts
const PUBLIC_PATHS = ['/', '/login', '/signup', '/forgot-password'];

// Inside middleware():
if (
  PUBLIC_PATHS.includes(pathname) ||
  pathname.startsWith('/_next') ||
  pathname.startsWith('/api') ||
  pathname.startsWith('/reset-password/')   // trailing slash — leaves the existing /reset-password page protected
) {
  return NextResponse.next();
}
```

**Crucially**, the prefix check uses `'/reset-password/'` (trailing slash). Without it the existing authenticated `/reset-password` page would also be whitelisted, breaking the `mustResetPassword` redirect.

### `LoginForm.tsx` — "Forgot password?" link

Add a small link below the password field pointing to `/forgot-password`. Uses the same shadcn primitive as the existing "Don't have an account? Sign up" link.

### `packages/api-client-ts/src/apiClient.ts`

Add to the `ApiClient` interface and implementation (placed alongside the existing public methods that pass `auth: false`):

```ts
// interface:
forgotPassword(email: string): Promise<void>;
resetPasswordWithToken(token: string, newPassword: string): Promise<void>;

// implementation (in createApiClient):
forgotPassword: (email) =>
  request('POST', '/auth/forgot-password',
    { body: { email }, auth: false }),
resetPasswordWithToken: (token, newPassword) =>
  request('POST', '/auth/reset-password',
    { body: { token, newPassword }, auth: false }),
```

Both must include `auth: false` (matches existing `signUp`, `login`, `refresh`). Without it the client attempts a 401-refresh loop for anonymous callers.

### i18n keys

Add to `apps/web/messages/{en,...}.json` (the locale files used by `next-intl`):

```
forgotPassword.title, forgotPassword.emailLabel, forgotPassword.submit, forgotPassword.success
resetPassword.title, resetPassword.passwordLabel, resetPassword.confirmLabel, resetPassword.submit
resetPassword.errorInvalid, resetPassword.errorExpired
loginForm.forgotPasswordLink
```

(Exact keys to be finalised at implementation time using the existing locale structure; rule: **no hardcoded user-facing strings**.)

### OpenAPI regen

Phase 2 ends with `make gen-api` so `packages/api-client-ts/src/types.gen.ts` picks up the new endpoints.

## Phasing

| Phase | Goal | Deliverable |
|-------|------|-------------|
| 0 | Skill harness + monolog test handler | Patch `.ai/skills/spec-writing/SKILL.md` (rule #11 — see "Skill Harness Patches" below), patch `references/spec-template.md` (JSON-example block), append `when@test:` block to existing `config/packages/monolog.yaml`, create `.ai/business-rules.md` with BR-U04 and BR-U05. `make lint && make test` green. |
| 1 | API domain + persistence | `PasswordRecoveryTokenId`, `PasswordRecoveryToken`, 3 exceptions, `PasswordRecoveryTokenRepository` + `PasswordRecoveryEmailSender` + `RefreshTokenRevoker` interfaces, `PasswordRecoveryTokenModel`, `DoctrinePasswordRecoveryTokenRepository`, `GesdinetRefreshTokenRevoker`, migration `Version20260610000001.php` (with hand-added partial unique index), aliases in `User/Infrastructure/Symfony/Resources/config/services.yaml`. `make lint && make test` green. |
| 2 | API application + infra + transport | `composer require symfony/mailer symfony/rate-limiter`, create `mailer.yaml` + `rate_limiter.yaml`, append `trusted_proxies` to `framework.yaml`, both commands + handlers + use cases, both controllers + DTOs + Nelmio attrs, `SymfonyPasswordRecoveryEmailSender`, update `security.yaml` (firewalls + access_control), add `MAILER_DSN` + `MAILER_FROM` + `APP_FRONTEND_URL` to `apps/api/.env`. Add `mailpit` service to `docker-compose.dev.yml`. PHPUnit functional tests (one case per file under `tests/Functional/User/Presentation/Http/{RequestPasswordRecovery,ResetPasswordWithToken}/`). End with `make gen-api`. `make lint && make test` green. |
| 3 | Frontend | `/forgot-password` and `/reset-password/[token]` pages + forms + actions; `Referrer-Policy: no-referrer` on the reset page; `LoginForm` link; `middleware.ts` PUBLIC_PATHS + `/reset-password/` prefix; api-client-ts methods with `auth: false`; i18n keys; admin-app no-op confirmation. `pnpm -C apps/web typecheck && lint && build` green. |

**Explicitly skipped — out of scope (local dev only)**:
- `.github/workflows/deploy.yml` `PROD_MAILER_DSN` secret.
- `ops/k8s/templates/_helpers.tpl`, `ops/k8s/values.{prod,}.yaml`, `ops/scripts/create-k8s-secrets.sh` — no K8s wiring.
- The JWT `fsGroup: 82` permission fix from PR #63 — production-only.
- Playwright e2e for the recovery flow — follow-up task (filed at end of PR description).

## Skill Harness Patches

### `.ai/skills/spec-writing/SKILL.md`

Append after the existing rule #10 ("Frontend boundary"):

```markdown
11. **API contract field alignment**: every endpoint with a request or response body must include an explicit JSON example — not just a DTO class name. The PHP DTO constructor property name (e.g., `$password`) is the exact JSON key that `#[MapRequestPayload]` deserializes, and the TypeScript client must use that exact key. A spec that only names the DTO class without showing the JSON shape is a **High** finding — it guarantees a field-name mismatch between backend and frontend.
```

### `.ai/skills/spec-writing/references/spec-template.md`

Replace the bullet block under `## API Contracts`:

```markdown
For each:
- Validation rules (route attributes + value-object construction)
- Error responses (404, 422, 401, 403, 409)
- OpenAPI annotations (Nelmio)
```

with:

```markdown
For each endpoint, include:
- Validation rules (route attributes + value-object construction)
- Error responses (404, 422, 401, 403, 409)
- OpenAPI annotations (Nelmio)
- **Explicit JSON body example** — required for every endpoint with a request or response body.
  The PHP DTO constructor property name is the exact JSON key (`#[MapRequestPayload]` uses property names directly).
  The TypeScript client must use the identical key. Do not leave field names implicit.

  ```jsonc
  // POST /auth/example — request
  { "fieldName": "value" }   // must match PHP DTO property $fieldName

  // POST /auth/example — response (if non-empty)
  { "id": "uuid" }
  ```
```

### `.ai/business-rules.md` (new)

```markdown
# Business Rules Registry

Domain rules enforced in the code. Add new rules here as they are introduced.

## User context

### BR-U04 — Password recovery tokens are single-use and time-limited
A `PasswordRecoveryToken` can be redeemed only once and expires 1 hour after creation. Any attempt to use an expired or already-redeemed token is rejected.
- **Context**: User
- **Enforcement**: `PasswordRecoveryToken::validate()` (`apps/api/src/User/Domain/PasswordRecoveryToken.php`) + partial unique index `(user_id) WHERE used_at IS NULL` on `password_recovery_tokens`
- **Exceptions**: `PasswordRecoveryTokenExpired`, `PasswordRecoveryTokenAlreadyUsed`

### BR-U05 — Password recovery never reveals user existence
`POST /auth/forgot-password` always returns 204 regardless of whether the supplied email is registered, preventing user enumeration.
- **Context**: User
- **Enforcement**: `RequestPasswordRecoveryUseCase` silently no-ops on `findByEmail()` returning null AND on `Email` VO construction failure
```

## Risks & Impact Review

| Risk | Severity | Affected area | Mitigation | Residual |
|------|----------|---------------|------------|----------|
| Token enumeration | Medium | Security | Always 204; rate-limit forgot-password (3/10 min/IP) | Low |
| Token theft from DB | Medium | Security | SHA-256 hash only; plain token never persisted | Low |
| Rate limiter sees proxy IP only | High → mitigated | Operability/Security | `trusted_proxies` added to `framework.yaml`; limiter scoped per real client IP | Low |
| CPU-DoS on `/auth/reset-password` via Argon2 | High → mitigated | Availability | Second rate limiter (10/min/IP) | Low |
| Concurrent token redemption (race) | High → mitigated | Security/Correctness | `findByTokenHashForUpdate` (`SELECT … FOR UPDATE`) + partial unique index on active tokens | Low |
| Stolen refresh token survives reset | Critical → mitigated | Security | `RefreshTokenRevoker::revokeAllFor(email)` called inside `ResetPasswordWithTokenUseCase` | Low |
| Token leak via `Referer` | Medium → mitigated | Security | `Referrer-Policy: no-referrer` on `/reset-password/[token]` | Low |
| Old unused token still valid after new request | Medium → mitigated | Security | `markAllUnusedAsUsed` superseding step in `RequestPasswordRecoveryUseCase` | Low |
| SMTP unavailable (Mailpit not running in fork) | Low | UX (dev only) | `TransportException` caught and logged; user still sees 204 | Low |
| Production forks default `MAILER_DSN` to Mailpit | Medium | Operability | Document explicitly in `apps/api/AGENTS.md`; no production-DSN supplied here | Low |
| `Email` VO construction throws inside use case | Medium → mitigated | Correctness | `try/catch(InvalidArgumentException)` → silent return preserves BR-U05 | Low |
| Test pollution from rate limiter | Medium → mitigated | Tests | `when@test:` block swaps `cache.rate_limiter` to array adapter and bumps limit to 1000/min | Low |
| Logged `TransportException` exposes recipient | Low | PII | Log `$e->getMessage()` only, not `$e` object | Low |

## Integration Coverage

One test per file, controller-named folder. Pattern matches existing `tests/Functional/User/Presentation/Http/SignUp/`.

| Test ID | Type | Path | Asserts |
|---------|------|------|---------|
| TC-PR-01 | PHPUnit Functional | `tests/Functional/User/Presentation/Http/RequestPasswordRecovery/ItIssuesTokenAndQueuesEmailForKnownUser.php` | Valid email → 204, exactly one row in `password_recovery_tokens` for that user, email captured by in-memory transport |
| TC-PR-02 | PHPUnit Functional | `tests/Functional/User/Presentation/Http/RequestPasswordRecovery/ItSilentlySucceedsForUnknownEmail.php` | Unknown email → 204, no token row |
| TC-PR-03 | PHPUnit Functional | `tests/Functional/User/Presentation/Http/RequestPasswordRecovery/ItRejectsMalformedEmail.php` | Invalid email format → 422 |
| TC-PR-04 | PHPUnit Functional | `tests/Functional/User/Presentation/Http/RequestPasswordRecovery/ItSupersedesPriorUnusedTokensForSameUser.php` | Two consecutive requests → first token is `usedAt != null` after second |
| TC-PR-05 | PHPUnit Functional | `tests/Functional/User/Presentation/Http/ResetPasswordWithToken/ItResetsPasswordAndMarksTokenUsed.php` | Valid token + valid password → 204, password changed, `usedAt` set |
| TC-PR-06 | PHPUnit Functional | `tests/Functional/User/Presentation/Http/ResetPasswordWithToken/ItRevokesAllRefreshTokensAfterReset.php` | After 204, no rows in `refresh_tokens` for user's email |
| TC-PR-07 | PHPUnit Functional | `tests/Functional/User/Presentation/Http/ResetPasswordWithToken/ItRejectsExpiredToken.php` | Expired token → 422 `password_recovery_token_expired` |
| TC-PR-08 | PHPUnit Functional | `tests/Functional/User/Presentation/Http/ResetPasswordWithToken/ItRejectsAlreadyUsedToken.php` | Used token → 422 `password_recovery_token_already_used` |
| TC-PR-09 | PHPUnit Functional | `tests/Functional/User/Presentation/Http/ResetPasswordWithToken/ItReturnsNotFoundForUnknownToken.php` | Unknown token → 404 |
| TC-PR-10 | PHPUnit Functional | `tests/Functional/User/Presentation/Http/ResetPasswordWithToken/ItRejectsWeakPassword.php` | <8 chars → 422 |

All tests inherit `FunctionalTestCase` (DB transactional rollback per test, verified at `apps/api/tests/Functional/FunctionalTestCase.php:24-37`). For email assertions, register Symfony's `null://` mailer in test env via the existing pattern, or pull the captured email via Symfony's `MailerAssertionsTrait` (test will use `'in-memory://default'` transport configured per the test profile).

Playwright e2e (`apps/web/e2e/forgot-password.spec.ts`, `reset-password.spec.ts`) is **deferred** to a follow-up task — filed in the PR description.

## Backward Compatibility

- [x] No removed/renamed event IDs (no new events introduced).
- [x] No removed/renamed API routes — `/auth/forgot-password` and `/auth/reset-password` are net new; `/api/users/me/reset-password` (self-reset) unchanged; `/api/admin/users/{id}/force-password-reset` (admin force-reset) unchanged.
- [x] No removed response fields.
- [x] No removed DB columns; new table only.
- [x] No change to existing `/reset-password` (authenticated) Next.js page — middleware whitelist uses trailing slash.
- [x] `ApiClient` interface additions are non-breaking for callers using `createApiClient()` (all current callers); a TS consumer that implements `ApiClient` themselves would need to add the two methods, but no such consumer exists in the monorepo.
- [x] `composer.lock` diff is additive only.

## Open Questions

(None — all design decisions are resolved. Implementation can proceed.)

## Final Compliance Report

(To be filled after implementation.)

## Changelog

| Date       | Change |
|------------|--------|
| 2026-06-10 | Spec drafted from PR #62 + #63 of jperdior/dungeon-manager. |
| 2026-06-10 | Revised after pre-implement audit: corrected command-handler naming (`*Command` + `*CommandHandler` + `*UseCase`); added `RefreshTokenRevoker` port + Gesdinet adapter; added `trusted_proxies` to `framework.yaml`; added rate limiter for `/auth/reset-password`; added partial unique index for atomic token redemption; documented `Email` VO failure handling in use case; consolidated `test/` config into `when@test:` blocks; tightened middleware whitelist to `'/reset-password/'` (trailing slash); quoted exact skill patches; added explicit file paths for controllers, DTOs, model, migration; added `make gen-api` step in Phase 2; added `auth: false` to TS client methods; added `Referrer-Policy: no-referrer` on reset page; added token-supersession step; added i18n key list; clarified `apps/admin` no-op; clarified Monolog is already installed (only `when@test:` block ported from #63); confirmed test naming (one case per file under controller folder). |
