# ops — Agents Guidelines

Container builds (`docker/`), Kubernetes skeleton (`k8s/`), and CI shell scripts (`ci/`). One image per runtime, no per-environment Dockerfiles.

## Always

- Build context is **monorepo root** (`../..` from `ops/docker/`). Dockerfiles must reach across workspace boundaries — never per-app build contexts.
- The API image is **shared** by `api` (FrankenPHP, serving HTTP on `:80`) and `worker` (`messenger:consume`). Only the runtime command differs. Worker mode (`APP_RUNTIME=Runtime\FrankenPhpSymfony\Runtime`) is set on the **`api` service definitions only** (compose/k8s env), never as a Dockerfile `ENV` — the shared `worker` must run under Symfony's default runtime.
- Production overlays go in `docker-compose.prod.yml` (if added) — never inline secrets, never override `APP_ENV` in the base file.
- Dev compose mounts source. Production compose / k8s manifests use baked images.

## Ask First

- Ask before adding a new top-level service to `docker-compose.base.yml`. Each one is another moving part and another `make` target.
- Ask before introducing a new PHP extension (it goes in the API Dockerfile's `install-php-extensions` line).
- Ask before changing the Compose service names — the Makefile and the docs reference them.
- Ask before deviating from the "one PHP image" rule. Splitting the worker image is a real cost.

## Never

- **Never** commit JWT keys, `.env.local`, or any file under `apps/api/config/jwt/`.
- **Never** add `--no-verify` style flags to CI scripts to mask failures.
- **Never** run `php-cs-fixer fix` (writing) inside CI — only `--dry-run`. Fixes are local-only.
- **Never** expose Caddy's admin API (`:2019`) or FrankenPHP metrics outside the container — the `Caddyfile` sets `admin off`. `/healthz` is the only intentionally public non-app endpoint.

## Layout

```
ops/
├── docker/
│   ├── api/         FrankenPHP Dockerfile + php.ini + Caddyfile
│   ├── web/         apps/web standalone Dockerfile
│   ├── admin/       apps/admin standalone Dockerfile
│   ├── docker-compose.base.yml   production-shaped stack (no source mounts)
│   └── docker-compose.dev.yml    overlays: source mounts + dev commands + exposed ports
├── k8s/
│   ├── Chart.yaml
│   ├── values.yaml
│   └── templates/   skeleton Helm chart (api pod, worker, web, admin, ingress)
```

CI lives in `.github/workflows/ci.yml` and invokes the root Makefile targets directly — there are no separate CI scripts.

## Compose Patterns

- **Base file** is production-shaped: builds happen, no source mounts, only the `api` host publish (`${API_PORT:-8080}:80`) + the web/admin ports are open.
- **Dev overlay** uses `!reset` (Compose v2.24+) to neutralize the base `build:` for web/admin so they run as plain `node:22-alpine` with `pnpm dev`. The PHP services keep their build but mount source over `/app`.
- The Makefile composes `-f base -f dev` for `make start`.

## Test stack crash self-diagnosis

The headless test stack (`wait-for-test-stack.sh`) detects a crash-looping
container within a few seconds (≈2 restart cycles) instead of hanging for the full
600s timeout. Readiness is gated on a `/tmp/stack-ready` sentinel the api startup
writes only after composer install + migrations succeed, so a crashed container is
never mistaken for "ready" (a `vendor/bin/phpstan` file-check would false-pass
because vendor is a persisted named volume → the dreaded `exec` Error 137). When a
service crashes during startup:

1. The crashed container's logs are dumped (last 80 lines).
2. The root cause is classified:
   - **TRUE OOM** only when `State.OOMKilled=true`.
   - **PHP memory_limit exhausted** when the PHP memory exhaustion message appears.
   - **PHP CODE ERROR** for parse/fatal/autowire errors — **NOT OOM**.
   - Generic fallback for unexpected exits.
3. `make lint-api` / `make test` output is self-diagnosing: read the banner,
   fix the PHP error, re-run. Do NOT raise memory limits unless the banner says
   `TRUE OOM`. Exit code 137 alone is **not** OOM.

Crash detection is keyed on `RestartCount` (all test services use
`restart: unless-stopped`), baselined per run so a crash-loop left over from a
previous `make lint-api` (which does not tear the stack down) is not mistaken for a
fresh crash — and a container recovering after you fix the error (exactly one
restart) is not falsely flagged.

This applies to the CI-gate path (`make lint`, `make test`, `make lint-api`,
etc.) through `make up-test`. The dev stack (`make start`) is not affected.

## Validation Commands

```bash
# Lint compose files (Compose v2+):
docker compose -f ops/docker/docker-compose.base.yml config -q
docker compose -f ops/docker/docker-compose.base.yml -f ops/docker/docker-compose.dev.yml config -q

# Test a build without starting:
docker compose -f ops/docker/docker-compose.base.yml build api
```

## K8s Note

The Helm chart is a **skeleton**. It is not part of `make start`. The default deployment story for this template is Compose. The chart exists so a project that outgrows Compose has a starting point — values.yaml + four Deployment manifests + an Ingress — rather than a blank page.
