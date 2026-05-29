# ops — Agents Guidelines

Container builds (`docker/`), Kubernetes skeleton (`k8s/`), and CI shell scripts (`ci/`). One image per runtime, no per-environment Dockerfiles.

## Always

- Build context is **monorepo root** (`../..` from `ops/docker/`). Dockerfiles must reach across workspace boundaries — never per-app build contexts.
- The API image is **shared** by `api` (php-fpm under nginx) and `worker` (`messenger:consume`). Only the runtime command differs.
- Production overlays go in `docker-compose.prod.yml` (if added) — never inline secrets, never override `APP_ENV` in the base file.
- Dev compose mounts source. Production compose / k8s manifests use baked images.

## Ask First

- Ask before adding a new top-level service to `docker-compose.base.yml`. Each one is another moving part and another `make` target.
- Ask before introducing a new PHP extension (it goes in the API Dockerfile).
- Ask before changing the Compose service names — the Makefile and the docs reference them.
- Ask before deviating from the "one PHP image" rule. Splitting the worker image is a real cost.

## Never

- **Never** commit JWT keys, `.env.local`, or any file under `apps/api/config/jwt/`.
- **Never** add `--no-verify` style flags to CI scripts to mask failures.
- **Never** run `php-cs-fixer fix` (writing) inside CI — only `--dry-run`. Fixes are local-only.
- **Never** modify `nginx/api.conf` to expose `/ping` or `/status` outside the cluster — they're FPM status endpoints.

## Layout

```
ops/
├── docker/
│   ├── api/         php-fpm Dockerfile + php.ini + php-fpm.conf
│   ├── nginx/       nginx config that fronts php-fpm
│   ├── web/         apps/web standalone Dockerfile
│   ├── admin/       apps/admin standalone Dockerfile
│   ├── docker-compose.base.yml   production-shaped stack (no source mounts)
│   └── docker-compose.dev.yml    overlays: source mounts + dev commands + exposed ports
├── k8s/
│   ├── Chart.yaml
│   ├── values.yaml
│   └── templates/   skeleton Helm chart (api+nginx pod, worker, web, admin, ingress)
└── ci/
    └── scripts/     install.sh, lint.sh, test.sh, build.sh — referenced from .github/workflows
```

## Compose Patterns

- **Base file** is production-shaped: builds happen, no source mounts, ports closed except via nginx.
- **Dev overlay** uses `!reset` (Compose v2.24+) to neutralize the base `build:` for web/admin so they run as plain `node:22-alpine` with `pnpm dev`. The PHP services keep their build but mount source over `/app`.
- The Makefile composes `-f base -f dev` for `make start`.

## Validation Commands

```bash
# Lint compose files (Compose v2+):
docker compose -f ops/docker/docker-compose.base.yml config -q
docker compose -f ops/docker/docker-compose.base.yml -f ops/docker/docker-compose.dev.yml config -q

# Test a build without starting:
docker compose -f ops/docker/docker-compose.base.yml build api

# Lint shell scripts:
shellcheck ops/ci/scripts/*.sh
```

## K8s Note

The Helm chart is a **skeleton**. It is not part of `make start`. The default deployment story for this template is Compose. The chart exists so a project that outgrows Compose has a starting point — values.yaml + four Deployment manifests + an Ingress — rather than a blank page.
