# Password Recovery (+ Monolog) — Local-Only Port

**Date**: 2026-06-10
**Status**: Draft
**Scope**: User bounded context (API) + Web frontend + Local dev infra (Mailpit + Monolog) + Spec-writing skill harness

## TLDR

Port the password-recovery flow from [jperdior/dungeon-manager#62](https://github.com/jperdior/dungeon-manager/pull/62) and the Monolog bundle from [#63](https://github.com/jperdior/dungeon-manager/pull/63) into this template. Scope is **local development only** — no K8s helm changes, no `PROD_MAILER_DSN` GitHub Actions secret, no production deploy wiring. The skill-harness improvements from #62 (spec-writing SKILL + spec-template) are ported because they apply to every future feature.

## Overview

Today the only password-reset path requires an authenticated session (`SelfResetPassword`) or an admin (`ForcePasswordReset`). A locked-out user has no recovery option. We add a self-service flow: submit email → receive time-limited link → set new password. Email is delivered to Mailpit in dev (`http://mailpit.localhost`). We do **not** wire any production SMTP credentials in this PR — production teams forking this template configure their own DSN.

Additionally, we install `symfony/monolog-bundle` with JSON-prod / line-dev / fingers-crossed-test handlers so future debugging surfaces exception context properly.

## Problem Statement

1. **Users who forget their password are stuck.** No recovery path exists.
2. **Logged exception context is invisible.** The default PSR logger drops the context array, so `[error] Unhandled exception` shows up with no class/file/line/trace.
3. **The spec-writing skill keeps producing specs that name a DTO class without showing the JSON shape** — this caused a field-name mismatch between backend (`$password`) and frontend (`newPassword`) in the upstream port. The skill check needs rule #12 enforcing an explicit JSON example.

## Proposed Solution

```
[User] → POST /auth/forgot-password (email)
              → RequestPasswordRecoveryHandler → UseCase
                    → PasswordRecoveryToken::create()       [new entity]
                    → PasswordRecoveryTokenRepository::save()
                    → PasswordRecoveryEmailSender::send()   [domain service interface]
                          → SymfonyPasswordRecoveryEmailSender  [infra impl]
                                → Symfony Mailer → Mailpit (smtp://mailpit:1025)

[User] → POST /auth/reset-password (token, password)
              → ResetPasswordWithTokenHandler → UseCase
                    → PasswordRecoveryTokenRepository::findByTokenHash()
                    → token.validate()  [throws expired/used]
                    → User::changePassword(hashedPassword)
                    → token.markAsUsed()
                    → UserRepository::save()
                    → PasswordRecoveryTokenRepository::save()
```

## Architecture

- **Bounded context**: User (no cross-context dependencies)
- **New entities**: `PasswordRecoveryToken`
- **New value objects**: `PasswordRecoveryTokenId` (only)
- **New domain exceptions**: `PasswordRecoveryTokenNotFound`, `PasswordRecoveryTokenExpired`, `PasswordRecoveryTokenAlreadyUsed`
- **New domain service interface**: `PasswordRecoveryEmailSender`
- **New repository interface**: `PasswordRecoveryTokenRepository`
- **Buses used**: CommandBus only (both endpoints are write-only)
- **Cross-context interaction**: none

### Token design

Plain token: `bin2hex(random_bytes(48))` → 96 hex chars. Goes in the email link. Never stored.
Stored: `hash('sha256', $plain)` (`tokenHash`, 64-char SHA-256). DB breach does not expose redeemable tokens.

## Data Models

### `PasswordRecoveryToken` entity

| Field        | Type                       | Notes |
|--------------|----------------------------|-------|
| `id`         | UUID v4                    | PK |
| `userId`     | UUID                       | FK → `users.id`, `ON DELETE CASCADE` |
| `tokenHash`  | VARCHAR(64)                | SHA-256 of plain token |
| `expiresAt`  | DATETIME_IMMUTABLE         | `createdAt + 1 hour` |
| `usedAt`     | DATETIME_IMMUTABLE NULL    | null until redeemed |
| `createdAt`  | DATETIME_IMMUTABLE         | |

- Index on `tokenHash` (lookup at redemption).
- One table: `password_recovery_tokens`.
- ORM mapping on `PasswordRecoveryTokenModel.php` (attribute), **not** on the domain entity.

### Domain entity skeleton

```php
// apps/api/src/User/Domain/PasswordRecoveryToken.php
final class PasswordRecoveryToken
{
    private function __construct(
        private PasswordRecoveryTokenId $id,
        private UserId $userId,
        private string $tokenHash,
        private \DateTimeImmutable $expiresAt,
        private ?\DateTimeImmutable $usedAt,
        private \DateTimeImmutable $createdAt,
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
        if ($now > $this->expiresAt) throw new PasswordRecoveryTokenExpired();
        if ($this->usedAt !== null)  throw new PasswordRecoveryTokenAlreadyUsed();
    }

    public function markAsUsed(\DateTimeImmutable $now): void { $this->usedAt = $now; }
}
```

## API Contracts

| Method | Path                     | Auth   | Request body                                   | Response       |
|--------|--------------------------|--------|------------------------------------------------|----------------|
| POST   | `/auth/forgot-password`  | public | `{ "email": "user@example.com" }`              | `204 No Content` |
| POST   | `/auth/reset-password`   | public | `{ "token": "<96 hex>", "newPassword": "..." }` | `204 No Content` |

### Field-name alignment

The PHP DTO property name **is** the JSON key (Symfony `#[MapRequestPayload]` deserializes by property name). The TS client and the Server Action `FormData` keys must use the same names. Concrete shapes:

```jsonc
// POST /auth/forgot-password — request
{ "email": "user@example.com" }
// matches ForgotPasswordRequest { public readonly string $email }

// POST /auth/reset-password — request
{ "token": "a1b2c3...96hex", "newPassword": "MyNewPassword123" }
// matches ResetPasswordWithTokenRequest { public readonly string $token; public readonly string $newPassword }
```

### `POST /auth/forgot-password`

- **Validation**: `email` valid RFC email (route DTO `#[Assert\Email]`).
- **Behaviour**: silently succeed (204) if email unknown — never leak user existence (BR-U05).
- **Rate limiting**: Symfony `RateLimiter`, fixed-window, 3 requests / 10 min per IP. Returns 429 when exceeded.
- **Errors**: 422 invalid email; 429 rate-limit.

### `POST /auth/reset-password`

- **Validation**: `token` matches `/^[a-f0-9]{96}$/` (`#[Assert\Regex]`), `newPassword` via `PlainPassword` VO (8–4096 chars).
- **Behaviour**: SHA-256 hash the supplied token, look it up, validate, change password, mark used.
- **Errors**:
  - `404 password_recovery_token_not_found`
  - `422 password_recovery_token_expired`
  - `422 password_recovery_token_already_used`
  - `422` weak password.

## Application Layer

### Commands

```
apps/api/src/User/Application/Command/RequestPasswordRecovery/
  RequestPasswordRecovery.php            (command DTO: email)
  RequestPasswordRecoveryHandler.php
  RequestPasswordRecoveryUseCase.php     (finds user → silent if missing → create + persist + send email)

apps/api/src/User/Application/Command/ResetPasswordWithToken/
  ResetPasswordWithToken.php             (command DTO: token, newPassword)
  ResetPasswordWithTokenHandler.php
  ResetPasswordWithTokenUseCase.php      (find by hash → validate → changePassword → markUsed)
```

The use-case-out-of-handler split matches the existing `SelfResetPassword` / `ForcePasswordReset` layout in this context.

### Domain service interface

```php
// apps/api/src/User/Domain/PasswordRecoveryEmailSender.php
interface PasswordRecoveryEmailSender
{
    public function send(Email $to, string $plainToken): void;
}
```

### Infrastructure email sender

```php
// apps/api/src/User/Infrastructure/Mail/SymfonyPasswordRecoveryEmailSender.php
final readonly class SymfonyPasswordRecoveryEmailSender implements PasswordRecoveryEmailSender
{
    public function __construct(
        private MailerInterface $mailer,
        private LoggerInterface $logger,
        private string $frontendUrl,
    ) {}

    public function send(Email $to, string $plainToken): void
    {
        $resetUrl = rtrim($this->frontendUrl, '/') . '/reset-password/' . $plainToken;
        $email = (new SymfonyEmail())
            ->from('noreply@jperdior.local')
            ->to((string) $to)
            ->subject('Reset your password')
            ->text("Click to reset your password (valid for 1 hour):\n\n{$resetUrl}")
            ->html("<p>Click to reset your password (valid for 1 hour):</p><p><a href=\"{$resetUrl}\">Reset password</a></p>");

        try {
            $this->mailer->send($email);
        } catch (TransportException $e) {
            $this->logger->error('password_recovery_email_send_failed', ['exception' => $e]);
            // Swallow to preserve the 204 contract.
        }
    }
}
```

## Configuration

### Composer additions

```
symfony/mailer          ^7.4
symfony/rate-limiter    ^7.4
symfony/monolog-bundle  ^3.10        # from PR #63
```

### Symfony bundles (`apps/api/config/bundles.php`)

```php
Symfony\Bundle\MonologBundle\MonologBundle::class => ['all' => true],
```

### `config/packages/mailer.yaml` (new)

```yaml
framework:
    mailer:
        dsn: '%env(MAILER_DSN)%'
```

### `config/packages/rate_limiter.yaml` (new)

```yaml
framework:
    rate_limiter:
        forgot_password:
            policy: fixed_window
            limit: 3
            interval: '10 minutes'
```

### `config/packages/security.yaml` — two new firewalls + access_control update

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

```yaml
access_control:
    - { path: ^/auth/(login|signup|refresh|forgot-password|reset-password), roles: PUBLIC_ACCESS }
    ...
```

### `config/packages/test/cache.yaml` (new) — disables rate limiting in tests

```yaml
framework:
    cache:
        pools:
            cache.rate_limiter:
                adapter: cache.adapter.array
```

### `config/packages/test/rate_limiter.yaml` (new) — bumps test limits

```yaml
framework:
    rate_limiter:
        forgot_password:
            policy: fixed_window
            limit: 1000
            interval: '1 minute'
```

### `config/packages/monolog.yaml` (new — from PR #63)

```yaml
monolog:
    channels: ['deprecation']

when@prod:
    monolog:
        handlers:
            main:
                type: stream
                path: 'php://stderr'
                level: info
                formatter: monolog.formatter.json
                channels: ['!event', '!deprecation']
            console:
                type: console
                process_psr_3_messages: false
                channels: ['!event', '!doctrine', '!console']

when@dev:
    monolog:
        handlers:
            main:
                type: stream
                path: 'php://stderr'
                level: debug
                channels: ['!event']
            console:
                type: console
                process_psr_3_messages: false
                channels: ['!event', '!doctrine', '!console']

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

### `config/services.yaml` — sender constructor argument

```yaml
App\User\Infrastructure\Mail\SymfonyPasswordRecoveryEmailSender:
    arguments:
        $frontendUrl: '%env(APP_FRONTEND_URL)%'
```

### `src/User/Infrastructure/Symfony/Resources/config/services.yaml`

```yaml
App\User\Domain\PasswordRecoveryTokenRepository:
    alias: App\User\Infrastructure\Persistence\DoctrinePasswordRecoveryTokenRepository

App\User\Domain\PasswordRecoveryEmailSender:
    alias: App\User\Infrastructure\Mail\SymfonyPasswordRecoveryEmailSender
```

### `apps/api/.env` additions

```ini
###> symfony/mailer ###
MAILER_DSN=smtp://mailpit:1025
###< symfony/mailer ###

###> password recovery ###
APP_FRONTEND_URL=http://web.localhost
###< password recovery ###
```

## Local Dev Infrastructure

### Mailpit service (in `ops/docker/docker-compose.dev.yml`)

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

Mailpit listens on `1025` (SMTP) internally — `MAILER_DSN=smtp://mailpit:1025` reaches it via the Docker network. UI is `http://mailpit.localhost`.

### `CI: "true"` env var

Add `CI: "true"` to web and admin service env in `docker-compose.dev.yml` (fixes a pnpm TTY noise in dev containers — pulled in from PR #62).

## Frontend Plan

### New routes

| Route                          | File                                                                       | Notes                              |
|--------------------------------|----------------------------------------------------------------------------|------------------------------------|
| `/forgot-password`             | `apps/web/src/app/forgot-password/{page,ForgotPasswordForm,actions}.tsx/ts` | Server page + client form via `useActionState` |
| `/reset-password/[token]`      | `apps/web/src/app/reset-password/[token]/{page,ResetPasswordWithTokenForm,actions}.tsx/ts` | Token segment passed as a prop to the form |

The existing `/reset-password` (authenticated reset) route is unaffected — Next.js routes `/reset-password` and `/reset-password/[token]` to distinct segments.

### Forms

Both use shadcn `Form` + `react-hook-form` + `zod`, dispatched via Server Actions.

- **ForgotPasswordForm** — `z.object({ email: z.string().email() })`. On submit, regardless of outcome, render "Check your email — if an account exists, a link has been sent." (BR-U05).
- **ResetPasswordWithTokenForm** — `z.object({ newPassword: z.string().min(8).max(4096), confirm: z.string() }).refine(...)`. On 404 → "This reset link is invalid." On 422 → "This reset link has expired or has already been used." On success → `redirect('/login?reset=1')`.

### Server Actions

Both actions use `createApiClient({ baseUrl: process.env.INTERNAL_API_URL ?? 'http://nginx:80' })` from `@jperdior/api-client-ts` (server entry). The forgot-password action silently swallows API errors so the UI does not reveal whether the email exists.

### `apps/web/src/middleware.ts` — add the two paths

```ts
const PUBLIC_PATHS = ['/', '/login', '/signup', '/forgot-password'];
// + allow /reset-password/* via prefix check
```

The matcher already excludes `_next/static`, so we only need to permit `/reset-password/<token>` by extending the early-return condition to include `pathname.startsWith('/reset-password')`.

### `LoginForm.tsx` — "Forgot password?" link

Add a small link below the password field pointing to `/forgot-password`.

### `packages/api-client-ts/src/apiClient.ts`

Add two methods to the `ApiClient` interface and implementation:

```ts
forgotPassword(email: string): Promise<void>;
resetPasswordWithToken(token: string, newPassword: string): Promise<void>;
```

Both call the new endpoints with the request bodies shown in **API Contracts**. They are listed alongside the public auth methods (`signUp`, `login`, `refresh`).

## Phasing

| Phase | Goal | Deliverable |
|-------|------|-------------|
| 0 | Skill harness + Monolog | Patch `.ai/skills/spec-writing/SKILL.md` rule #12 and `references/spec-template.md` JSON-example block. Install `symfony/monolog-bundle`, add `bundles.php` entry, add `config/packages/monolog.yaml`. `make lint && make test` green. |
| 1 | API domain layer | `PasswordRecoveryToken`, `PasswordRecoveryTokenId`, 3 domain exceptions, `PasswordRecoveryTokenRepository` + `PasswordRecoveryEmailSender` interfaces, `PasswordRecoveryTokenModel`, `DoctrinePasswordRecoveryTokenRepository`, migration `password_recovery_tokens`. Aliases in `User/Infrastructure/Symfony/Resources/config/services.yaml`. `make lint && make test` green. |
| 2 | API application + infra + transport | `composer require symfony/mailer symfony/rate-limiter`, create `mailer.yaml`, `rate_limiter.yaml`, `test/cache.yaml`, `test/rate_limiter.yaml`, `SymfonyPasswordRecoveryEmailSender`, both use cases + handlers, both controllers + DTOs, update `security.yaml` (firewalls + access_control), add `MAILER_DSN` + `APP_FRONTEND_URL` to `apps/api/.env`. Add `mailpit` service and `CI: "true"` env to `docker-compose.dev.yml`. Functional tests for both endpoints. `make lint && make test` green. |
| 3 | Frontend | `/forgot-password` and `/reset-password/[token]` pages + forms + actions; `LoginForm` link; `middleware.ts` public-path additions; api-client-ts methods. Type-check + ESLint + build pass. |

**Skipped — out of scope (local dev only)**:
- `.github/workflows/deploy.yml` `PROD_MAILER_DSN` — production teams set their own secret.
- `ops/k8s/templates/_helpers.tpl`, `values.{prod,}.yaml`, `create-k8s-secrets.sh` — no K8s production wiring.
- The K8s `fsGroup: 82` + JWT key permission fix from PR #63 — production-only.

## Risks & Impact Review

| Risk | Severity | Affected area | Mitigation | Residual |
|------|----------|---------------|------------|----------|
| Token enumeration | Medium | Security | Always 204; rate limiter 3/10 min/IP via `RateLimiter` | Low |
| Token theft from DB | Medium | Security | Only SHA-256 hash stored; plain token never persisted | Low |
| SMTP unavailable in dev | Low | UX | Mailpit runs in compose; `TransportException` is caught and logged | Low |
| Test pollution from rate limiter | Medium | Tests | `test/cache.yaml` swaps `cache.rate_limiter` to in-memory array; `test/rate_limiter.yaml` bumps limit to 1000/min | Low |
| Email enumeration via timing | Low | Security | Token creation + send is bounded but not constant-time; acceptable for a forking template | Low |
| Production teams forget to set `MAILER_DSN` | Medium | Operability | `null://null` is **not** the default (smtp://mailpit:1025 is). Production fork must override. Document in `apps/api/AGENTS.md`. | Low |

## Integration Coverage

| Test ID    | Type               | Path                                                                                             | Asserts |
|------------|--------------------|--------------------------------------------------------------------------------------------------|---------|
| TC-PR-01   | PHPUnit Functional | `tests/Functional/User/Presentation/Http/PasswordRecovery/RequestPasswordRecoveryTest.php`       | Valid email → 204, token row in DB |
| TC-PR-02   | PHPUnit Functional | same file                                                                                        | Unknown email → 204, no token row |
| TC-PR-03   | PHPUnit Functional | same file                                                                                        | Invalid email format → 422 |
| TC-PR-04   | PHPUnit Functional | `tests/Functional/User/Presentation/Http/PasswordRecovery/ResetPasswordWithTokenTest.php`        | Valid token + valid password → 204, password changed, `used_at` set |
| TC-PR-05   | PHPUnit Functional | same file                                                                                        | Expired token → 422 `password_recovery_token_expired` |
| TC-PR-06   | PHPUnit Functional | same file                                                                                        | Already-used token → 422 `password_recovery_token_already_used` |
| TC-PR-07   | PHPUnit Functional | same file                                                                                        | Unknown token → 404 |
| TC-PR-08   | PHPUnit Functional | same file                                                                                        | Weak password (<8 chars) → 422 |

E2E Playwright coverage is **deferred** — the existing template has minimal Playwright setup, and adding e2e for the recovery flow would expand scope. We rely on PHPUnit functional + manual verification against Mailpit for this PR.

## Backward Compatibility

- [x] No removed/renamed event IDs (no new events introduced)
- [x] No removed/renamed API routes
- [x] No removed response fields
- [x] No removed DB columns
- [x] No change to `/reset-password` (authenticated reset) — only `/reset-password/[token]` is new
- [x] No removed Symfony bundles
- [x] No `composer.lock` BC break (only additions)

## New Business Rules

These will be introduced as the project's first business rules. We create `.ai/business-rules.md` (file does not yet exist in this template) with the two new rules below — no backfill of older rules, since they are not currently documented elsewhere.

### BR-U04 — Password recovery tokens are single-use and time-limited
A `PasswordRecoveryToken` can be redeemed only once and expires 1 hour after creation. Any attempt to use an expired or already-redeemed token is rejected.
- **Context**: User
- **Enforcement**: `PasswordRecoveryToken::validate()` — throws `PasswordRecoveryTokenExpired` / `PasswordRecoveryTokenAlreadyUsed`

### BR-U05 — Password recovery never reveals user existence
`POST /auth/forgot-password` always returns 204 regardless of whether the email is registered.
- **Context**: User
- **Enforcement**: `RequestPasswordRecoveryUseCase` silently no-ops if `UserRepository::findByEmail()` returns null

## Skill Harness Changes (porting from PR #62)

1. **`.ai/skills/spec-writing/SKILL.md`** — append review heuristic #12 (API contract field alignment).
2. **`.ai/skills/spec-writing/references/spec-template.md`** — replace the bullet list under `## API Contracts` with the explicit JSON-example block.
3. **`.ai/business-rules.md`** — create the file with BR-U04 and BR-U05.

## Open Questions

(None — all design decisions inherit from PR #62 and have been adapted for this template's `web.localhost` host and single `.env` file. Confirm with reviewer before implementation.)

## Final Compliance Report

(To be filled after implementation.)

## Changelog

| Date       | Change |
|------------|--------|
| 2026-06-10 | Spec drafted from PR #62 + #63 of jperdior/dungeon-manager. |
