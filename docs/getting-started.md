# Getting Started

Clone, run one command, and have a working stack in under 5 minutes.

## Prerequisites

| Tool | Minimum version | Notes |
|------|----------------|-------|
| Docker | 24+ | Docker Desktop 4.27+ includes this |
| Docker Compose | v2.24+ | Bundled with Docker Desktop; check with `docker compose version` |
| make | any | Pre-installed on macOS and most Linux distros |

Everything else (PHP, Composer, Node.js, pnpm) runs inside containers. No local language runtimes needed.

---

## 1. Clone

```bash
git clone <your-repo-url> my-project
cd my-project
```

---

## 2. Bootstrap

```bash
sudo make init
```

> `sudo` is required because `make init` patches `/etc/hosts`. The script is idempotent — if the entries are already present it skips them and does not prompt again.

`make init` does three things in order:

1. Copies `.env.dist` → `.env.local` (skipped if it already exists)
2. Patches `/etc/hosts` for Traefik `*.localhost` routing (adds `127.0.0.1 api.localhost web.localhost admin.localhost`; on macOS `*.localhost` resolves automatically but the script still runs and exits immediately)
3. Installs AI skills as Claude Code slash commands into `.claude/skills/` (uses only `sh`, `awk`, `grep`, `sed` — no extra runtimes needed)

When init completes, start the stack:

```bash
make start
```

First boot takes **2–5 minutes**: Docker pulls base images, builds, runs `composer install`, generates the JWT keypair, creates the Postgres DB, and runs Doctrine migrations. Subsequent starts are seconds.

Wait for the log line `[OK] Cache for the "dev" environment` before making requests. Hit `Ctrl-C` to detach from logs; the stack stays running.

---

## 3. Review secrets

`.env.local` is gitignored and ships with development defaults. Before exposing the stack to any network, change at minimum:

| Variable | Action |
|----------|--------|
| `APP_SECRET` | `openssl rand -hex 32` |
| `JWT_PASSPHRASE` | any strong passphrase; the keypair is generated from this |
| `POSTGRES_PASSWORD` / `DATABASE_URL` | change from the default `app`/`app` |

For pure local development the defaults are fine.

---

## 4. Verify the stack

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

The Swagger UI is at `http://api.localhost/api/doc`.

---

## 5. Service URLs

| URL | Service |
|-----|---------|
| `http://api.localhost` | Symfony API (nginx → php-fpm) |
| `http://api.localhost/api/doc` | Swagger UI |
| `http://web.localhost` | Next.js public app |
| `http://admin.localhost` | Next.js admin panel |
| `http://localhost:8080` | Traefik dashboard |

---

## 6. Create the first admin

```bash
# Sign up via the API (or the web app), then promote:
make seed-admin EMAIL=me@example.com
```

`seed-admin` runs `app:user:promote-admin` inside the `api` container. The user must exist first. After promotion, `http://admin.localhost` is accessible with those credentials.

---

## 7. Set up the test database

```bash
make setup-test-db
make test
```

`make test` runs PHPUnit (unit + functional) and the JS test suite — all inside containers. No local runtime needed.

---

## Daily workflow

```bash
make start          # build images + start stack
make stop           # stop and remove containers
make logs           # tail all container logs (Ctrl-C to exit)
make api-shell      # bash shell inside the api container
make lint           # PHPStan + cs-fixer + deptrac + tsc + eslint (all in containers)
make test           # phpunit + pnpm test (all in containers)
make test-api       # phpunit only (faster)
make migrate        # apply pending Doctrine migrations
make migrate-diff   # generate a migration from entity changes
make db-reset       # drop + recreate + migrate (DANGEROUS — local dev only)
make gen-api        # regenerate packages/api-client-ts from OpenAPI spec
make seed-admin EMAIL=x  # promote a user to ROLE_ADMIN
```

Run `make help` for the full list.

---

## AI skills (slash commands)

After `make init`, the `.ai/skills/` directory is symlinked into `.claude/skills/`, registering each skill as a Claude Code slash command. Type `/` in Claude Code to see the full list. Common ones:

| Command | What it does |
|---------|-------------|
| `/customize-project` | Renames template placeholders and adds project context to AGENTS.md — run once after cloning |
| `/spec-writing` | Designs a feature spec-first (recommended entry point for any non-trivial feature) |
| `/new-feature` | Creates a worktree + branch for a new feature |
| `/scaffold-bounded-context` | Scaffolds the 4 DDD layers for a new context |
| `/add-command` | Adds a CQRS command + handler |
| `/add-query` | Adds a CQRS query + handler |
| `/add-route` | Adds an HTTP controller + route |
| `/scaffold-nextjs-page` | Scaffolds a Next.js page |

To install skills for a different agent (Cursor, Codex) or to add optional tiers:

```bash
sh scripts/install-skills.sh                    # interactive — prompts for agent
sh scripts/install-skills.sh --target cursor    # Cursor → .cursor/rules/
sh scripts/install-skills.sh --with automation  # default + automation tier
sh scripts/install-skills.sh --list             # show all tiers and skills
```

---

## Adding your first bounded context

The template ships with `User` as the reference context. To add a new one:

```bash
# Recommended — design first, then implement
/spec-writing        # brainstorm + write the spec
/implement-spec      # implement from the approved spec (runs scaffolding internally)

# Quick path — if the design is already clear
/scaffold-bounded-context
```

See [adding-a-bounded-context.md](adding-a-bounded-context.md) for the full walkthrough.

---

## Regenerating the TypeScript API client

After any backend change that affects the OpenAPI spec:

```bash
make gen-api
```

This hits the running API's `/api/doc.json` and regenerates `packages/api-client-ts/src/types.gen.ts`. Commit the result. Never edit the generated file by hand.

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
6. **Remove example content** — the `User` context is the reference; keep it. Any placeholder routes are yours to delete.
