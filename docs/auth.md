# Auth

JWT authentication with single-use refresh-token rotation. Stateless access tokens, rotating refresh tokens, and a frontend cookie strategy that keeps tokens out of `localStorage`.

---

## Design decisions

### Why JWT + refresh tokens instead of sessions?

Sessions require server-side state: either sticky load balancing or a shared session store. JWT is stateless — the PHP process verifies the token cryptographically without a DB round-trip. For a Symfony API consumed by a JavaScript frontend, JWT is the standard choice.

The downside of pure JWT is revocation: a signed token is valid until it expires, even if the user logs out or changes their password. We mitigate this by:

1. **Short access token TTL** (default 1 hour). The window where a leaked token remains usable is bounded.
2. **Single-use refresh tokens with rotation.** Each refresh call issues a new refresh token and revokes the previous one. A leaked refresh token is detectable (reuse = revoke everything) and bounded in time (30-day TTL).

### Why RS256 (asymmetric) instead of HS256 (symmetric)?

HS256 uses the same key to sign and verify. Any service that can verify tokens can also forge them. RS256 uses a private key to sign and a public key to verify. You can distribute the public key to multiple services (read models, edge functions) without giving them forgery capability. Future-proofing for a microservices split costs nothing now.

### Why HttpOnly cookies for both tokens?

`localStorage` is readable by any JavaScript on the page, including injected scripts from third-party ads, analytics, or XSS. A token in `localStorage` is one XSS away from exfiltration.

The frontends never see tokens in JavaScript. Both the access token (`at`) and the refresh token (`rt`) live in `HttpOnly`, `SameSite=Lax` cookies (`Secure` in production), written by `@jperdior/auth-server`'s `persistTokens()` during sign-in. Server Components and Server Actions call the API through `apiClient()` from `@jperdior/api-client-ts/server`, which reads the access-token cookie and, on a 401, calls `/auth/refresh` with the refresh-token cookie and persists the rotated pair. The Next.js proxy (built with `createAuthProxy` from `@jperdior/auth-server`) only checks cookie presence for route protection — token validation stays server-side in the API.

---

## Flow

### Sign up

```
POST /auth/signup
{ "email": "me@example.com", "password": "secret123" }

→ 201 { "id": "<uuid>" }
```

Creates a `User` aggregate with `ROLE_USER`, hashes the password with argon2id, emits `UserRegistered`, persists. The aggregate's `HashedPassword` value object is opaque — the hash never leaves the persistence layer.

### Log in

```
POST /auth/login
{ "email": "me@example.com", "password": "secret123" }

→ 200 {
    "token": "<access-jwt>",
    "refresh_token": "<opaque-token>"
  }
```

Symfony's `json_login` authenticator calls `UserProvider::loadUserByIdentifier()`, verifies the password, then delegates to Lexik's `JWTTokenManagerInterface` to issue the access token. Gesdinet listens to `AuthenticationSuccessEvent` and appends the refresh token to the response.

There is no controller for `/auth/login`. The route is registered in `src/User/Infrastructure/Symfony/Resources/config/routes.yaml` and the firewall intercepts it before any controller runs.

### Access a protected endpoint

```
GET /api/me
Authorization: Bearer <access-jwt>

→ 200 { "id": "...", "email": "...", "roles": [...] }
```

Lexik's `JWTAuthenticator` validates the signature (RS256), checks expiry, calls `UserProvider::loadUserByIdentifier()` to hydrate the `SecurityUser`, and sets the security token. The controller receives the authenticated user.

### Refresh the access token

```
POST /auth/refresh
{ "refresh_token": "<opaque-token>" }

→ 200 {
    "token": "<new-access-jwt>",
    "refresh_token": "<new-opaque-token>"
  }
```

Gesdinet verifies the refresh token exists and is not expired. It revokes the old token, issues a new one, and calls Lexik to issue a new access token — all in one transaction. Reusing a revoked refresh token invalidates the entire chain, forcing a re-login.

### Invalid or expired access token

```
GET /api/me
Authorization: Bearer <expired-jwt>

→ 401 { "code": 401, "message": "Expired JWT Token" }
```

The Next.js proxy catches this, calls `/auth/refresh` server-side, updates the cookie, and retries the original request — transparent to the user.

---

## Token details

| | Access token | Refresh token |
|--|--|--|
| Format | JWT (RS256) | Opaque random string |
| Storage | Memory (Zustand) | `HttpOnly` cookie |
| TTL env | `JWT_TTL` (default: `3600`) | `JWT_REFRESH_TTL` (default: `2592000`) |
| Persisted | No — verified cryptographically | Yes — `refresh_tokens` table |
| Revocable | Only by expiry | Yes — invalidated on each rotation |
| Rotation | N/A | Single-use: new token on every `/auth/refresh` |

---

## JWT keypair

Generated automatically on first `make start`:

```bash
# Manual regeneration:
make jwt-keys
```

Keys live in `apps/api/config/jwt/` (gitignored). The `api_jwt` Docker named volume persists them across container restarts. Clear the volume with `make clean` to regenerate from the current `JWT_PASSPHRASE`.

In production, mount keys from Kubernetes Secrets or Docker secrets. Set `JWT_SECRET_KEY` and `JWT_PUBLIC_KEY` to absolute paths. Never commit private keys.

---

## Roles

| Role | Granted by | Enforced by |
|------|-----------|-------------|
| `ROLE_USER` | sign-up | `IS_AUTHENTICATED_FULLY` on `/api/**` |
| `ROLE_ADMIN` | `make seed-admin EMAIL=…` or `PromoteToAdminCommand` | `ROLE_ADMIN` on `/api/admin/**` |

Role strings are stored in the `users.roles` JSON column. The `User` aggregate holds `list<string>` internally (because Doctrine JSON hydration returns strings) and exposes `roles(): list<Role>` (enum conversion) and `roleStrings(): list<string>` (persistence/JWT).

---

## Firewall summary

```
/auth/signup    → public (noop firewall)
/auth/login     → json_login (Lexik success/failure handlers)
/auth/refresh   → Gesdinet refresh_jwt
/api/doc        → public
/api/admin/**   → stateless JWT + ROLE_ADMIN
/api/**         → stateless JWT + IS_AUTHENTICATED_FULLY
```

See `apps/api/config/packages/security.yaml` for the full configuration.

---

## Adding a protected endpoint

Standard case — no `security.yaml` changes needed:

1. Place the route under `/api/` — the `api` firewall handles JWT validation automatically.
2. The user is available via `$this->getUser()` in the controller (which returns a `SecurityUser`).

Admin-only:

```php
#[Route('/api/admin/something', methods: ['GET'])]
#[IsGranted('ROLE_ADMIN')]
public function __invoke(): JsonResponse { ... }
```

Or place it under `/api/admin/` — the `admin_api` firewall enforces `ROLE_ADMIN` for the entire prefix.

---

## Password security

- Algorithm: **argon2id** via Symfony's `NativePasswordHasher`.
- The `User` aggregate accepts `PlainPassword` on sign-up, calls `PasswordHasherInterface::hash()`, stores `HashedPassword`.
- `PlainPassword` is a DTO, not a value object — it does length validation only, never persisted.
- `HashedPassword` is opaque: once constructed, the hash string is never exposed outside the domain.
- The hash is never logged, never returned from any endpoint, never stored in a cache.
