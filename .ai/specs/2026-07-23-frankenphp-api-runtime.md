# Migrate the API runtime from php-fpm + nginx to FrankenPHP

## TLDR

Replace the two-process API runtime (`php-fpm` on `:9000` fronted by a separate `nginx`
reverse proxy) with a single **FrankenPHP** container that serves HTTP directly, running in
**worker mode** (the Symfony kernel is booted once and kept resident, streaming many requests
through the hot kernel — the 2–4× throughput win). `symfony/runtime` is already a dependency,
so app-side wiring is `runtime/frankenphp-symfony` + `APP_RUNTIME` + the trusted-proxy fix.
Gated on a statefulness audit, a container-runtime smoke job, and the e2e journey.

**Decisions (locked):** base image `dunglas/frankenphp:*-php8.4-bookworm`; the internal URL
contract is renamed `http://nginx:80` → `http://api:80`; **worker mode from the start** (no
classic intermediate); the k8s skeleton collapses to a single container.

## Overview

The API is one image today but two processes: `php-fpm` (FastCGI on `:9000`) and an `nginx`
reverse proxy doing `try_files` + FastCGI pass. FrankenPHP (Caddy with PHP embedded)
terminates HTTP itself, collapsing those into one process, and brings HTTP/2/3 + compression
for free. Its **worker mode** boots the Symfony kernel once and keeps it hot across requests
(typically a 2–4× throughput win over per-request FPM boot). As a template, the payoff is a
simpler runtime and a modern default that projects inherit.

## Problem Statement

- **Two moving parts for one job** — every environment wires an `nginx` service *and* an
  `api` service, plus `nginx/api.conf`, `php-fpm.conf`, and FastCGI param plumbing (including
  `if_not_empty` guards at `nginx/api.conf:38` that exist only to stop FPM crashing on an
  empty `X-Forwarded-Host`).
- **A cold kernel on every request** — FPM rebuilds the DI container/routing per request.
- **No modern transport** — no HTTP/2/3 or built-in compression without extra nginx config.

## Proposed Solution

Single FrankenPHP container per environment. A `Caddyfile` replaces `nginx/api.conf`; the
`php_server` directive handles the Symfony front-controller routing. Auto-HTTPS is disabled
(TLS terminates upstream — Traefik in dev, ingress in prod), so FrankenPHP serves plain HTTP
on `:80` — the same port `nginx` served, so Traefik labels, the `${API_PORT:-8080}` host
publish, and `INTERNAL_API_URL` all just point at `api:80`.

```
BEFORE                                     AFTER
  Traefik ─▶ nginx:80 ─FastCGI▶ api:9000     Traefik ─▶ api:80  (FrankenPHP: Caddy + PHP)
  web/admin ─▶ http://nginx:80               web/admin ─▶ http://api:80
```

FrankenPHP runs in **worker mode** from the start: `runtime/frankenphp-symfony` +
`APP_RUNTIME` keep the kernel resident, and worker mode is switched on per-environment via the
`FRANKENPHP_CONFIG` env var (dev/e2e add FrankenPHP's `watch` so live code reload is
preserved; prod runs without watch). Worker mode is set **on the api service definitions
only**, never as an image ENV, so the shared `worker` (messenger) process keeps Symfony's
default runtime. The `framework.yaml` `trusted_proxies` uses explicit private-range CIDRs
(not the `REMOTE_ADDR` sentinel) to stay correct under a long-lived worker (symfony#57283).

## Architecture

- **Bounded context(s) affected**: none — pure infrastructure/runtime change. No `Domain`/
  `Application`/`Presentation`/bus code changes. `deptrac`/layer rules unaffected. The one
  app-config touch is `framework.yaml` `trusted_proxies` (see C2 below).
- **Buses used**: n/a. The three Messenger buses keep running synchronously in-request.

### The shared-image constraint (worker vs api) — CRITICAL

The API image is **shared** by two runtimes: `api` (serves HTTP) and `worker`
(`php bin/console messenger:consume`, `ops/k8s/templates/worker.yaml:23-26`, commented in
`docker-compose.base.yml:66-89`). Therefore:

- `APP_RUNTIME=Runtime\FrankenPhpSymfony\Runtime` and `FRANKENPHP_CONFIG` are set
  **only on the `api` service definitions** (compose + k8s), **never as a Dockerfile `ENV`**.
  A console command inheriting the FrankenPHP runtime is invalid — the worker must run under
  Symfony's default runtime. The image default `CMD` is `frankenphp run`; the worker keeps
  overriding it with its `php bin/console …` command, unaffected.

### Files in scope

| File | Change |
|------|--------|
| `ops/docker/api/Dockerfile` | **Full stage rewrite** (not just `FROM`): a shared `base` stage on `dunglas/frankenphp:1-php8.4-bookworm` runs `install-php-extensions`; `builder` and `runtime` both derive from it. The current Alpine-`.so`-copy strategy (`Dockerfile:60-62`) is **discarded** — musl `.so` files are ABI-incompatible with Debian. Drop the `php-fpm.conf` COPY + ordering comment (`:66-69`), `EXPOSE 9000`→`80`, `CMD php-fpm`→`frankenphp run --config /etc/frankenphp/Caddyfile`. Keep `USER www-data` (the FrankenPHP binary ships `cap_net_bind_service`, so non-root binds `:80`). `APP_RUNTIME` is **not** an image ENV. |
| `ops/docker/api/Caddyfile` | **New** — replaces `nginx/api.conf`. See full content below. |
| `ops/docker/nginx/api.conf`, `ops/docker/nginx/` | **Removed** (file + dir). |
| `ops/docker/api/php-fpm.conf` | **Removed** (no FPM). |
| `ops/docker/api/php.ini`, `php-dev.ini` | Retained; opcache/preload kept (preload user = `www-data`, matches). |
| `apps/api/bin/start` | Final `exec php-fpm --nodaemonize` → `exec frankenphp run --config /etc/frankenphp/Caddyfile`. Dev/test/e2e keep launching via `sh bin/start`. |
| `ops/docker/docker-compose.base.yml` | Remove `nginx`; `api` gains `ports: ["${API_PORT:-8080}:80"]` (inherits nginx's host publish) + `expose: ["80"]` + worker env (`APP_RUNTIME` + `FRANKENPHP_CONFIG=worker …`, prod: no watch); web/admin `depends_on: api` at `condition: service_started`; `INTERNAL_API_URL` default → `http://api:80`. |
| `ops/docker/docker-compose.dev.yml` | Remove `nginx`; move its Traefik `api` router labels onto `api`; `!reset []` the `api` `ports`; add worker env with `watch` (dev live reload). |
| `ops/docker/docker-compose.test.yml` | Remove the `nginx` neutralizer stanza; `!reset []` the `api` host publish (keep the stack port-free/parallel-safe). `api` still idles `sleep infinity` (gate execs phpunit — no HTTP). |
| `ops/docker/docker-compose.e2e.yml` | Remove the `nginx` stanza. e2e is `base+dev+e2e`, so `api` inherits dev's worker env (`watch`) — e2e exercises worker mode. |
| `Makefile` | `test-e2e` service list `… api nginx web` → `… api web` (`:237`). |
| `ops/scripts/wait-for-e2e-stack.sh` | Header-comment only (it probes api+web, never nginx — no logic change). |
| `ops/scripts/wait-for-stack.sh` | No change (probes via Traefik host headers). |
| `ops/k8s/templates/api.yaml` | Collapse api+nginx two-container pod → **single FrankenPHP container**; **keep the port `name: http` on `containerPort: 80`** (Service `targetPort: http` + ingress backend depend on it); drop the `fpm`/`:9000` port, the `nginx` container, the `nginx-conf` volume/mount, the ConfigMap **and** its `{{ .Files.Get "../docker/nginx/api.conf" }}` (else `helm template` renders an orphan); move liveness/readiness probes onto the api container; add `APP_RUNTIME`/`FRANKENPHP_CONFIG` as **container-specific env** here (appended after the shared `appEnv` include — NOT in the shared ConfigMap, which `worker.yaml` also reads). |
| `ops/k8s/values.yaml` | Point probes at `/healthz` (served by Caddy) instead of `/api/doc`; leave `ingress.className: nginx` (that's the ingress *controller*, unrelated). |
| `ops/k8s/templates/web.yaml`, `admin.yaml` | Confirm `INTERNAL_API_URL: "http://api"` still resolves (Service `api` still listens on `:80`); no value change needed, listed for completeness. |
| `ops/k8s/templates/worker.yaml` | Confirm it does **not** set `APP_RUNTIME` (default runtime for the console consumer). |
| `packages/api-client-ts/src/server.ts` | **In scope (was missing).** Fallback `?? 'http://api:8080'` (`:8`) → `?? 'http://api:80'`. This default feeds both `apiClient()` and `@jperdior/auth-server-ts` sign-in; `:8080` no longer resolves after nginx's host publish moves. |
| `apps/web/.env.example`, `apps/admin/.env.example` | **In scope (was missing).** `INTERNAL_API_URL=http://api:8080` → `http://api:80`. `NEXT_PUBLIC_API_URL=http://localhost:8080` stays (browser → host publish, still `:8080`). |
| `apps/api/config/packages/framework.yaml` | Replace the `REMOTE_ADDR` sentinel with explicit private-range CIDRs (see C2) to dodge symfony#57283 under a long-lived worker; comment updated. |
| `.github/workflows/ci.yml` | Add `ops/docker/**` + `Makefile` to the `php` paths-filter (so ops PRs run *some* gate — closes the "ops change = zero CI" gap), **and** add an `api-runtime-smoke` job that builds the api image, boots it, and asserts `php -m` lists all 8 extensions + `GET /healthz` = 200. This is the only CI job that exercises FrankenPHP. |
| `.env.dist` | `INTERNAL_API_URL=http://nginx:80` → `http://api:80` (`:51`). |
| `apps/api/composer.json` + `composer.lock` | Add `runtime/frankenphp-symfony` (via `composer require` so the lock updates). |
| Docs: `docs/ops.md` (`:12,14,27,61,79,117,154,193`), `docs/getting-started.md` (`:98,240`), `docs/ARCHITECTURE.md` (`:211`), `ops/AGENTS.md` (`:8` shared-image invariant, `:24` the "Never modify nginx/api.conf" rule, `:31-32,40,47` layout), `apps/api/AGENTS.md` (intro "php-fpm" line), root `AGENTS.md` (`:148` nginx mention) | Present-tense rewrite: "single FrankenPHP process" replaces "nginx + php-fpm"; fix the stale `docs/ops.md:193` claim that `ops/docker/**` is CI-filtered; remove the FPM-only rules. |

### Caddyfile

The global `frankenphp` directive stays bare — worker mode is injected per-environment via the
`FRANKENPHP_CONFIG` env var on the api service (dev/e2e append `watch`, prod does not), so one
Caddyfile serves every environment and `php_server` routes requests through the worker when one
is configured for `public/index.php`.

```caddyfile
{
	# TLS terminates upstream (Traefik/ingress); Caddy serves plain HTTP.
	auto_https off
	# Close the admin API (default localhost:2019) and expose no metrics.
	admin off
	# Worker mode is configured via the FRANKENPHP_CONFIG env var on the api service.
	frankenphp

	# Trust the upstream proxy network so Caddy resolves the real client IP.
	# The auth rate limiter keys on Symfony's getClientIp(), which reads
	# HTTP_X_FORWARDED_FOR under framework.yaml trusted_proxies; php_server forwards
	# the inbound header to PHP. Mirrors nginx's explicit set_real_ip_from intent.
	servers {
		trusted_proxies static private_ranges
		client_ip_headers X-Forwarded-For
	}
}

{$SERVER_NAME::80} {
	root * /app/apps/api/public
	encode zstd br gzip

	# Lightweight health endpoint for k8s probes + the CI smoke job (Caddy-served,
	# no kernel/DB — so it answers even before the worker warms up).
	respond /healthz 200

	# Subsumes nginx try_files + fastcgi_pass. public/ contains only index.php,
	# so the direct-.php-execution surface is index.php alone (parity with nginx's
	# `return 404` on other .php in practice).
	php_server
}
```

Worker mode env, set **on the api service only** (never as an image ENV):

- `APP_RUNTIME=Runtime\FrankenPhpSymfony\Runtime` (+ `composer require runtime/frankenphp-symfony`).
- prod: `FRANKENPHP_CONFIG=worker /app/apps/api/public/index.php`
- dev/e2e: the same `worker { … }` block plus `watch` for live code reload.

The runtime resets `kernel.reset`-tagged services (Doctrine `EntityManager` included) between
requests, so the kernel stays booted while per-request state is cleared.

## Data Models

n/a — no schema, entity, `*Model`, or migration changes.

## API Contracts

No HTTP route, DTO, or OpenAPI changes. The public request/response surface is byte-identical;
only the process terminating the connection changes. `openapi-drift` (boots the kernel to dump
the spec) doubles as a "kernel boots under the new image" check and must show **no diff**. A
new `GET /healthz` is served by Caddy (not Symfony) — it is not a Symfony route and does not
appear in the OpenAPI spec.

## Frontend Plan

No `apps/web` / `apps/admin` component or route changes. Config-only: the `INTERNAL_API_URL`
default (`http://api:80`) plus the `server.ts` fallback and both `.env.example` files move off
the dead `:8080`. Apps read the URL from env; no component code changes.

## Phasing

Single phase (worker mode from the start) — ends with `make test`, `make test-e2e`, and the CI
smoke job all green:

- Dockerfile stage-rewritten on FrankenPHP bookworm; `Caddyfile` (`admin off` + `trusted_proxies` + `/healthz`) added; `nginx` service + `nginx/api.conf` + `php-fpm.conf` removed.
- All 4 compose files, Makefile, wait scripts, k8s (single container, `http` port name kept), `.env.dist`, `server.ts`, both `.env.example`, CI (filter + smoke job), and docs updated; `INTERNAL_API_URL` = `http://api:80`.
- Worker mode wired: `runtime/frankenphp-symfony` + `APP_RUNTIME` + `FRANKENPHP_CONFIG` on the api services (dev/e2e with `watch`); `framework.yaml` `trusted_proxies` → explicit CIDRs.
- Statefulness audit (below) completed; `php -m` shows all 8 extensions; the e2e journey passes against the worker api.
- **Rollback**: whole-PR git revert (worker mode is not a runtime toggle here — it's the default). Dropping `APP_RUNTIME` + `FRANKENPHP_CONFIG` from the api services falls back to per-request (classic) execution without a rebuild, if ever needed as a hotfix.

**Statefulness audit checklist:**
- [ ] Doctrine `EntityManager` is reset per request (verify `kernel.reset` tag; no stale identity map across requests).
- [x] **Trusted-proxy `REMOTE_ADDR` sentinel replaced** — symfony#57283: in worker mode `setTrustedProxies()` runs once at `preBoot()` and freezes the `REMOTE_ADDR` sentinel against the first request. `framework.yaml` now uses explicit private-range CIDRs (`127.0.0.1,10.0.0.0/8,172.16.0.0/12,192.168.0.0/16`), not the sentinel, so the auth rate-limiter client-IP resolution is correct per request.
- [ ] No mutable request-scoped state on singleton services (scan `static` properties / in-memory caches).
- [ ] JWT/lexik key handling holds no per-user state across requests.
- [ ] Locks use `flock`/redis (already do); no process-global lock state survives a request.
- [ ] Messenger sync buses hold no cross-request handler state.
- [ ] Container RSS does not grow unbounded across the e2e journey (spot-check).

### OPEN ISSUE — Doctrine lazy-ghost proxy for `final` entities (blocks worker mode)

Under worker mode + prod, the first request to a route that resolves any Doctrine proxy fails with:

```
Uncaught Exception: Cannot generate lazy ghost: class "App\User\Infrastructure\Security\RefreshToken" is final.
```

`RefreshToken` (`apps/api/src/User/Infrastructure/Security/RefreshToken.php`) is a `final`
`#[ORM\Entity]`. Doctrine ORM 3 on PHP 8.4 uses native lazy objects for proxies, which cannot
wrap a `final` class. `php bin/console cache:warmup` hits the same error, so warming at build
is **not** a fix. Reproduces in the standalone prod image (`/healthz` and `/api/doc` work;
routeless `/` 500s because the request pipeline resolves the proxy).

**Candidate fixes (to decide + verify against the auth e2e journey):**
1. Drop `final` from `RefreshToken` (+ any other `final` mapped entity) — smallest change; verify no design rule requires `final` on persistence models.
2. Configure Doctrine to keep using the (deprecated) proxy autoloader instead of native lazy objects.
3. Confirm whether this also affects dev/test (it did not surface there — dev uses `APP_DEBUG=1`), and whether real auth (refresh-token load) trips it at runtime, not just `/`.

Status: **unresolved** — must be closed (and the `api-runtime-smoke` `GET /` assertion made
green) before this branch is PR-ready.

## Risks & Impact Review

| Risk | Severity | Affected area | Mitigation | Residual |
|------|----------|---------------|------------|----------|
| **Rate limiter keys on proxy IP / spoofable** — Caddy not configured to trust the proxy | **High** | Security (password-recovery limiter, `ForgotPasswordController.php:45`, `ResetPasswordWithTokenController.php:47`) | Explicit `servers { trusted_proxies … }` in the Caddyfile **and** Symfony `trusted_proxies` (unchanged P1) reading `X-Forwarded-For`; smoke job asserts the direct `web→api:80` no-XFF path does not 500 | Low |
| Worker-mode `REMOTE_ADDR` sentinel frozen at `preBoot` (symfony#57283) | High | Security (Phase 2) | Audit item + explicit CIDR `trusted_proxies` in `framework.yaml` | Low |
| Alpine `.so` copied onto Debian base → broken extensions | High | API boot | Full stage rewrite; `install-php-extensions` on the FrankenPHP base; `php -m` assertion in smoke job | Low |
| Worker inherits `APP_RUNTIME` → console consumer runs under wrong runtime | High | Async processing (Phase 2) | `APP_RUNTIME` set on api services only, never as image ENV; worker.yaml verified clean | Low |
| Missed internal-URL ref (`server.ts` / `.env.example` / compose) → Server Action 500 | Medium | web/admin SSR fetch | All refs enumerated in scope incl. the `:8080` fallbacks; e2e sign-in exercises the SSR path | Low |
| Lost host-port publisher (nginx owned `${API_PORT}:80`) | Medium | Host access to base/prod stack | `api` inherits the `${API_PORT:-8080}:80` publish; dev `!reset`s it | Low |
| k8s `.Files.Get` on deleted nginx config renders an orphan ConfigMap | Medium | Helm template | Remove ConfigMap + `.Files.Get` atomically with the file; no helm gate exists, so verify by `helm template` | Low |
| CI never exercised the container runtime | Medium | Coverage | New `api-runtime-smoke` CI job builds + boots the image; `ops/docker/**` added to the filter | Low |
| Ext parity gap on bookworm/glibc (`amqp`/`redis`/preload) | Medium | API boot | `install-php-extensions` covers all 8; `php -m` + openapi-drift + smoke job | Low |
| Non-root prod image can't bind `:80` | Low | Container start | FrankenPHP binary ships `cap_net_bind_service`; smoke job runs the prod-shaped image and curls `/healthz` (dev/test overlays run as root, so this is the only non-root check) | Low |
| Unbounded worker memory growth | Low–Med | Prod | Per-request reset; document optional worker restart threshold + monitoring in ops.md | Low–Med |

## Integration Coverage

No new application code → no new PHPUnit/Vitest units. The gate is behavioural + build:

| Test ID | Type | Path / target | Asserts |
|---------|------|---------------|---------|
| TC-FP-01 | Playwright e2e (existing) | `apps/web/**` auth journey | anon → sign up → log out → log in (both locales) passes against the FrankenPHP **worker** stack (e2e `api` inherits dev's worker env). Exercises the `web→api:80` SSR fetch path. |
| TC-FP-02 | CI smoke job (**new**) | `.github/workflows/ci.yml` `api-runtime-smoke` | Builds the api image; `php -m` lists `intl opcache pdo_pgsql bcmath zip redis apcu amqp`; container boots **as non-root**; `GET /healthz` = 200; `GET /` (no XFF) does not 500. |
| TC-FP-03 | PHPUnit (existing) | `make test-api` | Full suite green against the api container built on the FrankenPHP image. |
| TC-FP-04 | openapi-drift (existing) | `make gen-api` | Kernel boots under the new image; `openapi.json` + `types.gen.ts` show **no diff**. |
| TC-FP-05 | Manual + smoke | password-recovery rate-limit path | Limiter keys on the real client IP via the trusted-proxy chain (not the Traefik IP). |

> **Coverage honesty:** the native-PHP CI jobs (`setup-php`) never boot the runtime; the
> `api-runtime-smoke` job (TC-FP-02) and local `make test-e2e` (TC-FP-01) are the only gates
> that exercise FrankenPHP itself.

## Backward Compatibility

- [x] No removed/renamed event IDs
- [x] No removed/renamed API routes (adds Caddy-served `/healthz`, outside OpenAPI)
- [x] No removed response fields
- [x] No removed DB columns
- [ ] **Config default change**: `INTERNAL_API_URL` default `http://nginx:80` → `http://api:80`;
  the `server.ts` fallback and both `.env.example` move off the now-dead `:8080`. The `nginx`
  service is gone, so the old defaults would not resolve — intentional, documented in the
  changelog + `docs/ops.md` env table. Explicit `.env.local` overrides pointing at a real host
  are unaffected.
- [x] Compose project/volume names unchanged; no volume rename.
- [x] k8s Service name `api` + port name `http` preserved (Service/ingress selectors stay valid).
- [x] No committed secrets touched (`APP_RUNTIME` is a plain env var, api-service-scoped).

## Final Compliance Report

Infra-only diff — no `Domain`/`Application`/`Presentation`/bus code is touched (Phase 2's
`framework.yaml` `trusted_proxies` is config, not domain logic).

| Gate | Result |
|------|--------|
| Boundary | ✅ N/A — no context code imported. |
| Bus | ✅ N/A. |
| Mapping | ✅ N/A. |
| Validation | ✅ N/A. |
| Idempotency | ✅ N/A — no subscribers/workers added (worker runtime unchanged). |
| Auth | ✅ N/A — no endpoints added; rate-limiter client-IP integrity preserved (C1/C2). |
| Naming | ✅ N/A. |
| DateTime | ✅ N/A. |
| Final readonly | ✅ N/A. |
| `strict_types` | ✅ N/A — Caddyfile/YAML/Dockerfile/docs + one TS fallback + one composer dep (P2). |
| Tests | ✅ Behavioural + build coverage (TC-FP-01..05); adds a CI smoke job that boots the runtime. |
| BC | ✅ Only the `INTERNAL_API_URL` default + `:8080` fallbacks change; documented; no route/event/column/field removed. |

## Changelog

| Date | Change |
|------|--------|
| 2026-07-23 | Spec drafted. Decisions locked: bookworm base; `INTERNAL_API_URL` → `http://api:80`; two phases one PR; k8s single container. |
| 2026-07-23 | Revised after pre-implementation audit: full Dockerfile stage rewrite (Alpine `.so` ABI); shared-image worker/`APP_RUNTIME` constraint; explicit Caddy `trusted_proxies` + `admin off` + `/healthz`; `framework.yaml` CIDR fix (symfony#57283); added `server.ts` + both `.env.example` + `API_PORT` publish + k8s `http` port name/ConfigMap to scope; new `api-runtime-smoke` CI job + `ops/docker/**` filter; documented the CI-does-not-boot-the-runtime gap. |
| 2026-07-23 | Collapsed to a **single phase — worker mode from the start** (dropped the classic-mode intermediate) per user direction: worker enabled everywhere via `APP_RUNTIME` + `FRANKENPHP_CONFIG` on the api services, `framework.yaml` CIDRs applied up front. |
