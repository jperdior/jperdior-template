# Ops

How the template runs locally and how to ship it. The default stack is Docker Compose; the Helm chart in `ops/k8s/` is a starting point for projects that outgrow Compose.

## Layout

```
ops/
├── docker/          per-service Dockerfiles + nginx config + base/dev compose
├── k8s/             skeleton Helm chart (Deployments, Services, Ingress)
└── ci/scripts/      install/lint/test/build invoked by .github/workflows
```

## Runtime model

`apps/api` ships as **one image, two services**:

| Service | Command | Purpose |
|---------|---------|---------|
| `api`   | `php-fpm --nodaemonize` (behind nginx) | HTTP for every bounded context |
| `worker`| `php bin/console messenger:consume async` | Drains the doctrine:// Messenger transport |

This is a modular monolith. Adding a new bounded context means dropping a new `apps/api/code/src/<Context>/` folder — no new image, no new compose service, no new deployment.

`apps/web` and `apps/admin` are independent Next.js 15 standalone builds. They share UI primitives via the `@jperdior/ui-react` workspace package.

## `make start` — local dev

```bash
cp .env.dist .env.local
make start
```

This composes `ops/docker/docker-compose.base.yml` with `ops/docker/docker-compose.dev.yml` and brings up:

- `postgres:16-alpine` on 5432
- `redis:7-alpine` on 6379
- `api` (php-fpm) with source mounted at `/app/apps/api`
- `worker` (same image, different command)
- `nginx` exposing `${API_PORT:-8080}` and proxying to `api:9000`
- `web` (`node:22-alpine`) running `pnpm dev` on `${WEB_PORT:-3000}`
- `admin` (`node:22-alpine`) running `pnpm dev` on `${ADMIN_PORT:-3001}`

The `api` container auto-runs `composer install` and `lexik:jwt:generate-keypair --skip-if-exists` on first boot. JWT keys persist in the `api_jwt` named volume; clear it with `make clean` to regenerate.

### Common follow-ups after `make start`

```bash
make migrate              # apply Doctrine migrations against the dev DB
make setup-test-db        # create + migrate the test DB
make gen-api              # regenerate packages/api-client-ts/src/types.gen.ts from OpenAPI
make seed-admin EMAIL=…   # grant ROLE_ADMIN to an existing user
```

## Compose file layering

- `docker-compose.base.yml` is **production-shaped**: builds happen, no source mounts, no exposed DB/Redis ports, ports closed except via nginx.
- `docker-compose.dev.yml` overlays dev-only behavior. Uses Compose-spec `!reset` (v2.24+) to neutralize the production `build:` for the JS apps so they run as plain `node:22-alpine` with `pnpm dev` and a mounted repo.

This lets the same compose layout serve both `make start` (dev) and a future `docker-compose.prod.yml` overlay without duplicating service definitions.

## Environment variables

The full list lives in `.env.dist`. Highlights:

| Var | Where it's used | Notes |
|-----|----------------|-------|
| `DATABASE_URL` | API + worker | `postgresql://app:app@postgres:5432/app` in compose |
| `MESSENGER_TRANSPORT_DSN` | API + worker | `doctrine://default?auto_setup=1` — no broker required to boot |
| `JWT_SECRET_KEY` / `JWT_PUBLIC_KEY` / `JWT_PASSPHRASE` | API + worker | `make jwt-keys` generates the pair in `apps/api/code/config/jwt/` |
| `CORS_ALLOW_ORIGIN` | API | Regex allowing `localhost` / `127.0.0.1` in dev |
| `NEXT_PUBLIC_API_URL` | web + admin | Browser-visible API URL — exposed at build time, baked into JS |
| `INTERNAL_API_URL` | web + admin | Server-side fetch URL — `http://nginx:80` inside compose |

## Production: Compose

The base compose file is production-ready. Add a `docker-compose.prod.yml` overlay that:

- Sets `APP_ENV=prod`, `APP_DEBUG=0`, `NODE_ENV=production`.
- Pins image tags rather than building locally.
- Mounts JWT keys + DB password from Docker secrets or an external `.env` file.
- Adds a TLS-terminating reverse proxy (Traefik, Caddy) in front of nginx if not delegated to a CDN.

## When to graduate to Kubernetes

Compose handles a single-host deployment comfortably. Move to the Helm chart when any of these become true:

- You need rolling deployments with zero downtime.
- You need to scale `worker` independently of `api` past one host.
- You need pod-level autoscaling.
- Your platform team mandates k8s.

The chart in `ops/k8s/` is a **skeleton**:

- `api` runs as a pod with two containers (php-fpm + nginx sidecar). Nginx config is loaded from a ConfigMap that references `ops/docker/nginx/api.conf`.
- `worker` is a separate Deployment using the same image.
- `web` and `admin` are independent Deployments.
- Sensitive values (DATABASE_URL, JWT_PASSPHRASE) come from pre-existing Kubernetes Secrets — names are listed under `secrets:` in `values.yaml`. The chart does NOT create them.
- Ingress is off by default; turn it on per `values.<env>.yaml`.

```bash
helm install jperdior ops/k8s -f my-values.yaml
```

## CI

`.github/workflows/` (Phase 11) invokes the scripts in `ops/ci/scripts/`:

- `install.sh` — composer for every PHP workspace + pnpm install
- `lint.sh` — PHPStan + cs-fixer + deptrac on PHP; tsc + eslint on JS
- `test.sh` — PHPUnit (unit + functional) + pnpm test
- `build.sh` — production Composer + Next.js standalone builds

The Playwright suite is not part of `test.sh`; it runs against an ephemeral compose stack in its own CI job (also Phase 11).
