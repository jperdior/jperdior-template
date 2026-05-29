# Getting Started

## Prerequisites

- Docker + Docker Compose v2.24+
- Node 22 + pnpm 9 (`corepack enable`)
- PHP 8.4 + Composer 2 (only for running tools outside Docker)

## First boot

```bash
git clone <your-repo-url> my-project
cd my-project
cp .env.dist .env.local   # keep defaults for local dev
make start                # builds images, starts stack, tails logs
```

`make start` boots: **Postgres → Redis → API (php-fpm + nginx) → Worker → Web → Admin**.

On first boot the `api` container:
1. Runs `composer install`
2. Generates JWT keypair under `apps/api/config/jwt/`
3. Creates the database and runs all migrations
4. Warms the Symfony cache
5. Starts php-fpm

Wait for the log line `[OK] Cache for the "dev" environment...` before making requests.

## Verify it works

```bash
# Sign up
curl -X POST http://localhost:8080/auth/signup \
  -H 'Content-Type: application/json' \
  -d '{"email":"me@example.com","password":"secret123"}'
# → {"id":"<uuid>"}

# Log in
curl -X POST http://localhost:8080/auth/login \
  -H 'Content-Type: application/json' \
  -d '{"email":"me@example.com","password":"secret123"}'
# → {"token":"<jwt>","refresh_token":"<token>"}
```

Open the browser:
| URL | Description |
|-----|-------------|
| `http://localhost:3000` | Public web app (signup → notes) |
| `http://localhost:3001` | Admin panel |
| `http://localhost:8080/api/doc` | OpenAPI docs |

## Daily workflow

```bash
make start        # start stack
make stop         # stop stack
make logs         # tail all logs
make api-shell    # shell inside api container
make migrate      # run pending migrations
make lint         # PHPStan + cs-fixer + deptrac + tsc + eslint
make test         # phpunit + pnpm test
```

## Adding a bounded context

See `docs/adding-a-bounded-context.md` or run the `/scaffold-bounded-context` skill.

## Promoting a user to admin

```bash
make seed-admin EMAIL=me@example.com
```

## Resetting the database

```bash
make db-reset    # drops + recreates + migrates (DANGEROUS — local only)
```
