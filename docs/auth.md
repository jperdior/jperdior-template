# Auth

JWT authentication via `lexik/jwt-authentication-bundle` + refresh-token rotation via `gesdinet/jwt-refresh-token-bundle`.

## Flow

```
POST /auth/signup   →  create User, return { id }
POST /auth/login    →  Symfony json_login → Lexik issues access token + Gesdinet issues refresh token
GET  /api/me        →  validate JWT, return current user payload
POST /auth/refresh  →  rotate refresh token, issue new access token
```

### Access token

- Format: JWT (RS256, signed with the private key at `apps/api/config/jwt/private.pem`)
- TTL: `JWT_TTL` env (default 3600 s / 1 h)
- Sent as: `Authorization: Bearer <token>` header
- Claim used as user identifier: `email`

### Refresh token

- Stored in the `refresh_tokens` DB table
- TTL: `JWT_REFRESH_TTL` env (default 2592000 s / 30 days)
- Single-use rotation: each `/auth/refresh` call invalidates the old token and issues a new one
- Sent as: `POST /auth/refresh` body `{ "refresh_token": "<token>" }`

## Frontend strategy

- **Access token** — stored in memory (Zustand store), never persisted to `localStorage`/`cookie`
- **Refresh token** — stored in an `HttpOnly` cookie set by the Next.js middleware layer
- On page load / token expiry the Next.js middleware calls `/auth/refresh` server-side and silently rotates

## JWT keypair

Generated automatically on first `make start`. For manual generation:

```bash
make jwt-keys
```

Keys live in `apps/api/config/jwt/` (gitignored). The `api_jwt` Docker volume persists them across container restarts.

In production set `JWT_SECRET_KEY` / `JWT_PUBLIC_KEY` to the absolute path of the keys on the host (or use Kubernetes secrets and mount them).

## Roles

| Role | Granted by | Enforced by |
|------|-----------|-------------|
| `ROLE_USER` | signup | `IS_AUTHENTICATED_FULLY` access-control rule |
| `ROLE_ADMIN` | `make seed-admin EMAIL=…` or `PromoteToAdminCommand` | `ROLE_ADMIN` access-control rule on `^/api/admin` |

## Security firewall config

```
/auth/signup    → public (no firewall)
/auth/login     → json_login (Lexik success/failure handlers)
/auth/refresh   → Gesdinet refresh_jwt
/api/doc        → public
/api/admin/**   → JWT + ROLE_ADMIN
/api/**         → JWT + IS_AUTHENTICATED_FULLY
```

See `apps/api/config/packages/security.yaml` for the full config.

## Adding a new protected endpoint

1. Place it under `/api/` — the `api` firewall handles JWT validation automatically
2. For admin-only: place under `/api/admin/` or add `#[IsGranted('ROLE_ADMIN')]` to the controller
3. No changes to `security.yaml` needed for standard cases
