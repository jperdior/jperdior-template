# Ops

How the template runs locally and how to ship it. The default deployment path is Docker Compose. The Helm chart skeleton under `ops/k8s/` is a starting point for projects that outgrow single-host Compose.

---

## Layout

```
ops/
├── docker/
│   ├── Dockerfile.api           PHP 8.4 + FPM + nginx in one image
│   ├── Dockerfile.web           Node 22 + Next.js standalone build
│   ├── nginx/api.conf           Nginx config: php-fpm upstream, /api/doc, CORS
│   ├── docker-compose.base.yml  Production-shaped base
│   └── docker-compose.dev.yml   Dev overlay: source mounts, pnpm dev, exposed ports
└── k8s/
    ├── Chart.yaml
    ├── values.yaml              Defaults; override per environment
    └── templates/               Deployments, Services, ConfigMaps, Ingress
```

---

## Runtime model

`apps/api` is **one image, one service** — `api` (nginx + php-fpm). The three Messenger buses (command, query, event) run synchronously inside the request. No worker process, no external broker, no message queue to operate.

This is a deliberate default for a template. Sync Messenger is simpler to understand and sufficient for most early-stage projects. When a specific command or event becomes a bottleneck (slow email, external API calls, bulk processing), you add an async transport for that route only — the template's `messenger.yaml` has the commented block ready.

`apps/web` and `apps/admin` are independent Next.js 15 standalone builds. They share UI primitives via the `@jperdior/ui-react` workspace package and hit the API via the generated `@jperdior/api-client-ts` client.

### Async processing with RabbitMQ

The template is ready for async processing when you need it. The API image includes the PHP `amqp` extension, and RabbitMQ + a worker container are defined as a Compose profile (`async`) — off by default.

To opt in:
1. Uncomment the transport block in `apps/api/config/packages/messenger.yaml` (instructions are in the comments)
2. Set `MESSENGER_TRANSPORT_DSN` in `.env.local`
3. Run `make start-async`

The domain code doesn't change. Only the transport wiring changes — commands you want deferred go in the `routing:` block, everything else keeps running synchronously.

---

## Local dev with `make start`

```bash
cp .env.dist .env.local
# edit .env.local as needed
make start
```

This merges `docker-compose.base.yml` with `docker-compose.dev.yml` and starts:

| Container | Image | Notes |
|-----------|-------|-------|
| `postgres` | `postgres:16-alpine` | Exposed on `5432` (dev only) |
| `redis` | `redis:7-alpine` | Exposed on `6379` (dev only) |
| `api` | Local Dockerfile.api build | Source mounted at `/app/apps/api` |
| `nginx` | `nginx:1.27-alpine` | Routes `api.localhost` → `api:9000` |
| `web` | `node:22-alpine` | `pnpm dev` with source mount |
| `admin` | `node:22-alpine` | `pnpm dev` with source mount |
| `traefik` | `traefik:v3` | Routes `*.localhost` domains |

With `make start-async` (profile `async`), two additional services start:

| Container | Image | Notes |
|-----------|-------|-------|
| `rabbitmq` | `rabbitmq:4-management-alpine` | AMQP on `5672`, management UI on `15672` |
| `worker` | Same image as `api` | `messenger:consume async -vv` |

The `api` container runs `bin/start` on boot:
1. `composer install` (skipped in prod: `--no-dev --optimize-autoloader`)
2. `lexik:jwt:generate-keypair --skip-if-exists`
3. `doctrine:database:create --if-not-exists`
4. `doctrine:migrations:migrate --no-interaction`
5. `cache:warmup`
6. `php-fpm`

JWT keys persist in the `api_jwt` named volume. Clear with `make clean` to regenerate.

---

## Compose file layering

`docker-compose.base.yml` is **production-shaped**:
- Images are built from Dockerfiles; no source mounts.
- DB and Redis ports are not exposed.
- No `pnpm dev` — Next.js runs from the standalone build.

`docker-compose.dev.yml` overlays dev-only behavior using Compose spec `!reset` (v2.24+):
- Overrides `api` and `worker` to mount the source.
- Replaces `web`/`admin` `build:` with plain `node:22-alpine` + `pnpm dev`.
- Exposes `postgres:5432` and `redis:6379` to the host for tools like TablePlus.

This pattern means the same service definitions serve both local dev and production without duplication. A `docker-compose.prod.yml` overlay just sets `APP_ENV=prod`, pins image tags, and adds secrets mounting.

---

## Environment variables

Full reference in `.env.dist`. Highlights for operators:

| Variable | Default | Change for production |
|----------|---------|----------------------|
| `APP_ENV` | `dev` | `prod` |
| `APP_SECRET` | `changeme` | **Yes** — `openssl rand -hex 32` |
| `APP_DEBUG` | `1` | `0` |
| `DATABASE_URL` | `postgresql://app:app@postgres:5432/app` | Real credentials |
| `JWT_PASSPHRASE` | `changeme` | **Yes** — strong passphrase |
| `JWT_TTL` | `3600` | Tune per risk appetite |
| `JWT_REFRESH_TTL` | `2592000` | 30 days default |
| `MESSENGER_TRANSPORT_DSN` | _(not set — sync by default)_ | Set to AMQP DSN when enabling async |
| `CORS_ALLOW_ORIGIN` | `^https?://.*\.localhost` | Your production domain regex |
| `NEXT_PUBLIC_API_URL` | `http://api.localhost` | Your production API URL |
| `INTERNAL_API_URL` | `http://nginx:80` | Internal hostname inside Compose/k8s |

---

## Production on a single host (Compose)

Add a `docker-compose.prod.yml`:

```yaml
# docker-compose.prod.yml
services:
  api:
    image: registry.example.com/my-project/api:${VERSION}
    environment:
      APP_ENV: prod
      APP_DEBUG: '0'
    secrets: [jwt_private, jwt_public, db_password]

  web:
    image: registry.example.com/my-project/web:${VERSION}

  admin:
    image: registry.example.com/my-project/admin:${VERSION}
```

Run with:

```bash
# Sync (no async processing):
docker compose -f ops/docker/docker-compose.base.yml \
               -f docker-compose.prod.yml up -d

# Async (RabbitMQ + worker):
docker compose -f ops/docker/docker-compose.base.yml \
               -f docker-compose.prod.yml --profile async up -d
```

Add Traefik or Caddy in front for TLS termination. The `INTERNAL_API_URL` should point to `http://nginx:80` (same Compose network).

---

## When to move to Kubernetes

Compose handles a single-host deployment comfortably for most early-stage projects. Move to the Helm chart when:

- **Rolling deployments** — you need zero-downtime updates with readiness gates.
- **Independent scaling** — `worker` needs more replicas than `api` (high async workload).
- **Multi-node** — the workload exceeds one host.
- **Platform mandate** — your organization requires k8s.

The `ops/k8s/` chart is a skeleton, not a production-hardened chart. You will need to:
- Set `secrets:` references in `values.yaml` for DATABASE_URL, JWT keys, etc.
- Configure `Ingress` rules for your cluster's ingress controller.
- Add `PodDisruptionBudgets` and `HorizontalPodAutoscalers` for production resilience.
- Wire `PersistentVolumeClaims` for the JWT keys volume (or use Kubernetes Secrets directly).

```bash
helm install my-project ops/k8s -f my-values.yaml
helm upgrade my-project ops/k8s -f my-values.yaml --atomic
```

---

## CI

`.github/workflows/ci.yml` invokes the same containerised Makefile targets developers run locally — the Makefile is the single author of the gate commands:

| CI job | Make target(s) | What it runs |
|--------|----------------|-------------|
| `php-lint` | `lint-shared-kernel`, `lint-api` | PHPStan + php-cs-fixer + deptrac |
| `php-tests` | `test-shared-kernel`, `test-api` | PHPUnit (shared kernel + Unit + Functional against the headless test stack) |
| `openapi-drift` | `gen-api` | Regenerates `openapi.json` + `types.gen.ts`; fails on diff |
| `js-lint` | `lint-web` | tsc + ESLint (apps + packages) |
| `js-tests` | `test-web` | Vitest (packages/auth-server-ts + apps/web + apps/admin) |
| `js-build` | `build-web` | Production Next.js builds |

See `.ai/skills/integration-tests/SKILL.md` for the testing layers and how to add a new test.

Every PR must pass all jobs before merge. Because CI calls the make targets, local `make lint && make test` green means CI green — the two cannot drift.

---

## Useful Makefile targets

```bash
make start              # build + start stack + tail logs
make start-async        # same + RabbitMQ broker + worker (set MESSENGER_TRANSPORT_DSN first)
make stop               # stop and remove containers
make logs               # tail all container logs
make api-shell          # bash inside the api container
make clean              # stop + remove volumes (including JWT keys)
make migrate            # apply pending Doctrine migrations
make migrate-diff       # generate migration from entity changes
make test               # phpunit + pnpm test
make test-api           # phpunit only
make lint               # phpstan + cs-fixer + deptrac + tsc + eslint
make lint-api           # PHP lint only
make build-web          # production Next.js build (both apps)
make gen-api            # regenerate packages/api-client-ts from OpenAPI spec
make seed-admin EMAIL=x # promote a user to ROLE_ADMIN
make db-reset           # drop + recreate + migrate (DANGEROUS — local only)
make jwt-keys           # regenerate JWT keypair
make worker-shell       # bash inside the worker container (requires make start-async)
```

---

## Before you ship: production checklist

This template gives you a solid structural foundation. Before going live, wire up the things that are intentionally out of scope:

| Concern | What to add |
|---------|------------|
| **Error reporting** | [Sentry](https://sentry.io) (`sentry/sentry-symfony`) — captures PHP exceptions with stack traces and request context |
| **Structured logging** | Monolog + a JSON formatter + a log aggregator (Loki, Datadog, CloudWatch). The default `var/log/` is not production-safe. |
| **Metrics / traces** | OpenTelemetry SDK (`open-telemetry/opentelemetry-auto-symfony`) or a Datadog/Prometheus integration |
| **Health endpoint** | Add `GET /health` returning `{"status":"ok"}` — used by load balancers and k8s readiness probes |
| **Rate limiting** | Symfony RateLimiter component on auth endpoints (`/auth/login`, `/auth/signup`) |
| **Secret management** | Docker secrets / Kubernetes Secrets / HashiCorp Vault. Never commit `.env.local`. |
| **Database backups** | Scheduled `pg_dump` — not provided here |
| **HTTPS** | TLS termination at the reverse proxy (Traefik with Let's Encrypt or CDN). The app itself is HTTP only. |
| **Dependency updates** | `dependabot` or `renovate` for automated PRs on outdated packages |

None of these are architectural — they're operational wiring that varies too much per project to bake in. The checklist tells you what to add; the template gives you everything to add it onto.
