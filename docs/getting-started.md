# Getting Started

## Prerequisites

- Docker + Docker Compose v2.24+
- Node 22 + pnpm 9 (`corepack enable`)
- PHP 8.4 + Composer 2 (only for running tools outside Docker)

## First boot

```bash
git clone <your-repo-url> my-project
cd my-project
make init
```

`make init` does everything in one shot:
1. Copies `.env.dist` → `.env.local` (skipped if already exists)
2. Adds `api.localhost`, `web.localhost`, `admin.localhost` to `/etc/hosts` (Linux only — macOS resolves `*.localhost` automatically)
3. Builds images, starts the stack, tails logs

On first boot the `api` container:
1. Runs `composer install`
2. Generates JWT keypair under `apps/api/config/jwt/`
3. Creates the database and runs all migrations
4. Warms the Symfony cache
5. Starts php-fpm

Wait for the log line `[OK] Cache for the "dev" environment...` before making requests.

> **Note:** Edit `.env.local` to change `APP_SECRET`, `JWT_PASSPHRASE`, or database credentials before exposing the stack to a network.

## Service URLs

All traffic goes through Traefik on port 80:

| URL | Description |
|-----|-------------|
| `http://api.localhost` | Symfony API (php-fpm via nginx) |
| `http://api.localhost/api/doc` | OpenAPI / Swagger UI |
| `http://web.localhost` | Public web app |
| `http://admin.localhost` | Admin panel |
| `http://localhost:8080` | Traefik dashboard (`make traefik`) |

## Verify it works

```bash
# Sign up
curl -X POST http://api.localhost/auth/signup \
  -H 'Content-Type: application/json' \
  -d '{"email":"me@example.com","password":"secret123"}'
# → {"id":"<uuid>"}

# Log in
curl -X POST http://api.localhost/auth/login \
  -H 'Content-Type: application/json' \
  -d '{"email":"me@example.com","password":"secret123"}'
# → {"token":"<jwt>","refresh_token":"<token>"}
```

## Daily workflow

```bash
make start        # start stack
make stop         # stop stack
make logs         # tail all logs
make traefik      # open Traefik dashboard
make api-shell    # shell inside api container
make migrate      # run pending migrations
make lint         # PHPStan + cs-fixer + deptrac + tsc + eslint
make test         # phpunit + pnpm test
```

## Adding a bounded context

See `docs/adding-a-bounded-context.md` or say "scaffold a new bounded context" to the agent.

## Promoting a user to admin

```bash
make seed-admin EMAIL=me@example.com
```

## Resetting the database

```bash
make db-reset    # drops + recreates + migrates (DANGEROUS — local only)
```
