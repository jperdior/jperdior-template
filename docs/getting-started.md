# Getting Started

From zero to a running stack in under 30 minutes.

## Prerequisites

| Tool | Minimum version | Notes |
|------|----------------|-------|
| Docker | 24+ | Docker Desktop 4.27+ includes this |
| Docker Compose | v2.24+ | Bundled with Docker Desktop; check with `docker compose version` |
| Node.js | 22 | For running JS tools outside Docker |
| pnpm | 9 | `corepack enable && corepack prepare pnpm@latest --activate` |
| PHP + Composer | 8.4 / 2 | Only needed to run tools locally, not to boot the stack |

> **macOS users**: `*.localhost` domains resolve automatically. No `/etc/hosts` edits needed.  
> **Linux users**: `make init` patches `/etc/hosts` for you.

---

## 1. Clone and configure

```bash
git clone <your-repo-url> my-project
cd my-project
cp .env.dist .env.local
```

`.env.local` contains all secrets and service URLs. The defaults work for local dev with Docker. Before exposing the stack to any network, change at minimum:

- `APP_SECRET` — generate with `openssl rand -hex 32`
- `JWT_PASSPHRASE` — any strong passphrase; the keypair is generated from this
- `POSTGRES_PASSWORD` / `DATABASE_URL` — change from the default `app`/`app`

---

## 2. Start the stack

```bash
make start
```

This runs `docker compose` with the base + dev overlay. First boot takes ~60–90 seconds. The `api` container:

1. Runs `composer install`
2. Generates the JWT keypair at `apps/api/config/jwt/` (skipped if already present)
3. Creates the Postgres database and runs all Doctrine migrations
4. Warms the Symfony cache in `dev` mode
5. Starts `php-fpm`

Wait for the log line that contains `[OK] Cache for the "dev" environment` before making requests. You can tail logs with `make logs` in another terminal.

---

## 3. Verify the stack

```bash
# Sign up a user
curl -s -X POST http://api.localhost/auth/signup \
  -H 'Content-Type: application/json' \
  -d '{"email":"me@example.com","password":"secret123"}' | jq
# → {"id":"<uuid>"}

# Log in
curl -s -X POST http://api.localhost/auth/login \
  -H 'Content-Type: application/json' \
  -d '{"email":"me@example.com","password":"secret123"}' | jq
# → {"token":"<jwt>","refresh_token":"<token>"}

# Use the token
TOKEN=<jwt from above>
curl -s http://api.localhost/api/me \
  -H "Authorization: Bearer $TOKEN" | jq
# → {"id":"<uuid>","email":"me@example.com","roles":["ROLE_USER"]}
```

The Swagger UI is at `http://api.localhost/api/doc` — all endpoints documented with Nelmio OpenAPI attributes.

---

## 4. Service URLs

| URL | Service |
|-----|---------|
| `http://api.localhost` | Symfony API (nginx → php-fpm) |
| `http://api.localhost/api/doc` | Swagger UI |
| `http://web.localhost` | Next.js public app (`pnpm dev`) |
| `http://admin.localhost` | Next.js admin panel (`pnpm dev`) |
| `http://localhost:8080` | Traefik dashboard |

---

## 5. Create the first admin

```bash
# Register via the API, then promote:
make seed-admin EMAIL=me@example.com
```

The `seed-admin` target runs `app:user:promote-admin` inside the `api` container. The user must already exist (created via signup). After promotion, the `/api/admin/users` endpoint is accessible.

---

## 6. Set up the test database

The functional test suite runs in a separate database with transaction rollback isolation (no data bleeds between tests):

```bash
make setup-test-db
make test
```

`make test` runs PHPUnit (unit + functional) inside the `api` container plus `pnpm test` for the JS workspace.

---

## Daily workflow

```bash
make start          # build images + start stack (sync Messenger, no broker)
make start-async    # same + RabbitMQ + worker (set MESSENGER_TRANSPORT_DSN first)
make stop           # stop and remove containers
make logs           # tail all container logs (Ctrl-C to exit)
make api-shell      # bash shell inside the api container
make lint           # PHPStan + cs-fixer + deptrac + tsc + eslint
make test           # phpunit + pnpm test
make test-api       # phpunit only (faster)
make migrate        # apply pending Doctrine migrations
make migrate-diff   # generate a migration from entity changes
make db-reset       # drop + recreate + migrate (DANGEROUS — local dev only)
make gen-api        # regenerate packages/api-client-ts from OpenAPI spec
make seed-admin EMAIL=x  # promote a user to ROLE_ADMIN
```

Run `make help` for the full list.

---

## Adding your first bounded context

The template ships with `User` as the reference context. To add a new one:

```bash
# Option A — use the AI skill (recommended)
/scaffold-bounded-context

# Option B — manual
mkdir -p apps/api/src/Orders/{Domain,Application,Infrastructure,Presentation}
# Then follow docs/adding-a-bounded-context.md
```

See [adding-a-bounded-context.md](adding-a-bounded-context.md) for the full walkthrough.

---

## Regenerating the TypeScript API client

After any backend change that affects the OpenAPI spec:

```bash
make gen-api
```

This hits the running API's `/api/doc.json` endpoint and regenerates `packages/api-client-ts/src/types.gen.ts`. Commit the result. Never edit the generated file by hand.

---

## Environment variables reference

The full list is in `.env.dist`. Key variables:

| Variable | Default | Notes |
|----------|---------|-------|
| `APP_ENV` | `dev` | Set to `prod` in production |
| `APP_SECRET` | `changeme` | **Change before any deployment** |
| `DATABASE_URL` | `postgresql://app:app@postgres:5432/app` | Compose service name as host |
| `DATABASE_TEST_URL` | separate DB name | Used by PHPUnit |
| `JWT_SECRET_KEY` | `%kernel.project_dir%/config/jwt/private.pem` | Auto-generated on first boot |
| `JWT_PUBLIC_KEY` | `%kernel.project_dir%/config/jwt/public.pem` | Auto-generated on first boot |
| `JWT_PASSPHRASE` | `changeme` | **Change before any deployment** |
| `JWT_TTL` | `3600` | Access token lifetime in seconds |
| `JWT_REFRESH_TTL` | `2592000` | Refresh token lifetime (30 days) |
| `MESSENGER_TRANSPORT_DSN` | `doctrine://default?auto_setup=1` | Doctrine transport — no broker needed |
| `CORS_ALLOW_ORIGIN` | `^https?://.*\.localhost` | Regex; extend for production origins |
| `NEXT_PUBLIC_API_URL` | `http://api.localhost` | Browser-visible API URL |
| `INTERNAL_API_URL` | `http://nginx:80` | Server-side fetch URL inside Compose |

---

## What to change for a real project

1. **Rename the PHP namespace** from `App\` to your own throughout `apps/api/src/`.
2. **Change `composer.json` `name`** in `apps/api/composer.json`.
3. **Change `package.json` names** in `apps/web`, `apps/admin`, `packages/*`.
4. **Set real secrets** — `APP_SECRET`, `JWT_PASSPHRASE`, Postgres credentials — in `.env.local` and in your deployment environment.
5. **Add your first bounded context** beyond `User`. The AI harness will scaffold it.
6. **Remove example content** — the `User` context is the reference; keep it. Any `Hello` or placeholder routes are yours to delete.
