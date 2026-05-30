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
├── k8s/
│   ├── Chart.yaml
│   ├── values.yaml              Defaults; override per environment
│   └── templates/               Deployments, Services, ConfigMaps, Ingress
└── ci/scripts/
    ├── install.sh               composer install + pnpm install for all workspaces
    ├── lint.sh                  PHPStan + cs-fixer + deptrac + tsc + ESLint
    ├── test.sh                  PHPUnit + pnpm test
    └── build.sh                 Production Composer + Next.js standalone builds
```

---

## Runtime model

`apps/api` ships as **one image, two services**:

| Service | Command | Purpose |
|---------|---------|---------|
| `api` | `php-fpm --nodaemonize` (behind nginx) | HTTP for every bounded context |
| `worker` | `php bin/console messenger:consume async` | Drains the `doctrine://` Messenger transport |

This is a modular monolith. Adding a new bounded context means dropping a new folder under `apps/api/src/<Context>/` — no new image, no new compose service, no new database, no new deployment.

The `worker` shares the same image and codebase as `api`. The only difference is the entry command. If you need to scale them independently (high write volume, slow background jobs) you can give `worker` its own replica count or resource limits in k8s — without splitting the code.

`apps/web` and `apps/admin` are independent Next.js 15 standalone builds. They share UI primitives via the `@jperdior/ui-react` workspace package and hit the API via the generated `@jperdior/api-client-ts` client.

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
| `worker` | Same image as `api` | Different command: `messenger:consume async` |
| `nginx` | `nginx:1.27-alpine` | Routes `api.localhost` → `api:9000` |
| `web` | `node:22-alpine` | `pnpm dev` with source mount |
| `admin` | `node:22-alpine` | `pnpm dev` with source mount |
| `traefik` | `traefik:v3` | Routes `*.localhost` domains |

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
| `MESSENGER_TRANSPORT_DSN` | `doctrine://default?auto_setup=1` | Swap for Redis/AMQP at scale |
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

  worker:
    image: registry.example.com/my-project/api:${VERSION}
    environment:
      APP_ENV: prod
    command: php bin/console messenger:consume async --time-limit=3600

  web:
    image: registry.example.com/my-project/web:${VERSION}

  admin:
    image: registry.example.com/my-project/admin:${VERSION}
```

Run with:

```bash
docker compose -f ops/docker/docker-compose.base.yml \
               -f docker-compose.prod.yml up -d
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

`.github/workflows/` calls the scripts in `ops/ci/scripts/`:

| Script | What it runs |
|--------|-------------|
| `install.sh` | `composer install` for every PHP workspace + `pnpm install` |
| `lint.sh` | PHPStan (level 8) + php-cs-fixer + deptrac + tsc + ESLint |
| `test.sh` | PHPUnit (unit + functional) + `pnpm test` |
| `build.sh` | Production `composer install --no-dev` + Next.js `pnpm build` |

The Playwright e2e suite is not part of `test.sh`. It runs in its own CI job against an ephemeral Compose stack — see `.ai/skills/integration-tests/SKILL.md`.

Every PR must pass lint + test before merge. The `make lint` and `make test` targets replicate these checks locally so you never push a red build.

---

## Useful Makefile targets

```bash
make start              # build + start stack + tail logs
make stop               # stop and remove containers
make logs               # tail all container logs
make api-shell          # bash inside the api container
make clean              # stop + remove volumes (including JWT keys)
make migrate            # apply pending Doctrine migrations
make migrate-diff       # generate migration from entity changes
make setup-test-db      # create + migrate the test DB
make test               # phpunit + pnpm test
make test-api           # phpunit only
make lint               # phpstan + cs-fixer + deptrac + tsc + eslint
make lint-api           # PHP lint only
make build-web          # production Next.js build (both apps)
make gen-api            # regenerate packages/api-client-ts from OpenAPI spec
make seed-admin EMAIL=x # promote a user to ROLE_ADMIN
make db-reset           # drop + recreate + migrate (DANGEROUS — local only)
make jwt-keys           # regenerate JWT keypair
```
