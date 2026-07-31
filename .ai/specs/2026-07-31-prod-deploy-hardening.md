# Prod Deploy Hardening — buildable web/admin images, readable JWT key, static assets

## TLDR

The template ships `ops/docker/*` and `ops/k8s/*` but **CI never exercises the prod
build/deploy path** (CI builds web via `make build-web`, not the Docker image), so the prod
artifacts are latently broken and every derived project rediscovers the same three failures
on first deploy. This spec makes the prod artifacts genuinely deploy-ready by fixing three
concrete, portable breakages:

1. **JWT signing key is unreadable in prod.** The Helm chart mounts `private.pem` as
   `0400` (root-only), but the api/worker containers run as `www-data` (uid/gid 33), so
   Lexik cannot read the key → `/auth/login` and signup return 500. Fix: mount `0440`
   (group-readable) + pod `securityContext.fsGroup: 33`.
2. **Static assets 404 in prod.** Next.js `output: 'standalone'` does **not** bundle
   `public/`. `apps/admin/public` is never copied into the runtime image; `apps/web/public`
   *is* copied but the directory does not exist in the repo, so the web image build actually
   **fails** (`COPY` of a missing source). Fix: guarantee both `public/` dirs exist and copy
   them into both runtime images.
3. **web/admin images don't build.** Both Dockerfiles copy a split `node_modules` from a
   `deps` stage and never install `packages/auth-server-ts` (a `transpilePackages` dep), so
   `next build` fails module-not-found. Fix: single full-tree install mirroring
   `ops/ci/scripts/js-workspace-install.sh`.

**Scope:** ops-only. No bounded context, no bus, no domain code, no API contract, no
migration. A generic `deploy.yml` CD workflow is explicitly **out of scope** (it would bake
in per-project infra assumptions — a separate, larger spec).

Ported from two upstream fixes discovered in a derived project: JWT key perms and the
web/admin image build + `public/` handling.

---

## Root cause: why CI is green but prod breaks

`.github/workflows/ci.yml` builds the frontend with `make build-web`, which runs
`pnpm -C apps/web build` on the host workspace where **all** `node_modules` are already
installed and `public/` is irrelevant to a dev build. The **Docker images**
(`ops/docker/{web,admin}/Dockerfile`) and the **Helm chart** (`ops/k8s/*`) are never built
or rendered by CI. So:

- A Dockerfile that fails to install `auth-server-ts` — green CI, broken image.
- A `COPY apps/web/public` against a non-existent dir — green CI, broken image.
- A `0400` JWT mount unreadable by `www-data` — green CI, 500 on first prod login.

This spec does not add image/helm builds to CI (that is the CD-workflow decision, out of
scope), but it **does** make the artifacts correct and adds a one-time local `docker build`
to the implementation verification gate so the fix is proven.

## Evidence (current template state)

- `ops/k8s/templates/_helpers.tpl` → `jperdior.jwtVolume`:
  `- { key: private.pem, path: private.pem, mode: 0400 }`.
- `ops/docker/api/Dockerfile`: `chown -R www-data:www-data … config/jwt …` then
  `USER www-data`. On `dunglas/frankenphp:1-php8.4-bookworm` (Debian), `www-data` is
  uid/gid **33**.
- `ops/k8s/templates/{api,worker}.yaml`: no `securityContext` on the pod template.
- `apps/web/public` and `apps/admin/public`: **do not exist** (`git ls-files` empty).
- `ops/docker/web/Dockerfile` runtime stage: `COPY --from=builder /repo/apps/web/public …`
  (fails when the dir is absent).
- `ops/docker/admin/Dockerfile` runtime stage: **no** public COPY.
- Both Dockerfiles: `deps` stage copies only `apps/{web,admin}`, `packages/ui-react`,
  `packages/api-client-ts` — **`packages/auth-server-ts` is missing**, yet both apps list
  `@jperdior/auth-server` in `transpilePackages`.

---

## Changes

### Phase 1 — JWT signing key readable by `www-data` (Helm only)

**`ops/k8s/templates/_helpers.tpl`** — in `jperdior.jwtVolume`, change the private key mode
and add a comment:

```
    items:
      # api/worker run as www-data (uid/gid 33). The private key is group-readable (0440)
      # and the pod sets fsGroup: 33; a root-only 0400 makes Lexik fail to load the signing
      # key → /auth/login 500s.
      - { key: private.pem, path: private.pem, mode: 0440 }
      - { key: public.pem,  path: public.pem,  mode: 0444 }
```

**`ops/k8s/templates/api.yaml`** — add to `spec.template.spec` (before `containers:`):

```yaml
      # www-data (uid/gid 33) is the FrankenPHP runtime user; fsGroup makes the mounted
      # JWT secret (private.pem 0440) group-readable so Lexik can sign tokens.
      securityContext:
        fsGroup: 33
```

**`ops/k8s/templates/worker.yaml`** — add the same `securityContext: { fsGroup: 33 }` to
`spec.template.spec` (before `containers:`).

**Verification:** `helm template ops/k8s` renders without error and the rendered api/worker
Deployments contain `fsGroup: 33`; the jwt volume renders `mode: 0440` for `private.pem`.

### Phase 2 — Buildable web/admin images + static `public/`

**New files** (guarantee the dirs exist so `COPY` always succeeds and users can drop assets):
- `apps/web/public/.gitkeep`
- `apps/admin/public/.gitkeep`

**`.dockerignore`** (repo root) — required by the `COPY . .` install so the build context
excludes host `node_modules`, build output, VCS, and worktrees. Create if absent; otherwise
ensure it covers at least:

```
**/node_modules
**/.next
**/.turbo
.git
.claude
apps/api/var
```

**`ops/docker/web/Dockerfile`** — collapse `deps`+`builder` into one builder stage that
installs the full workspace in one shot; keep the runtime stage (which already copies
`public/`, now non-failing):

```dockerfile
# syntax=docker/dockerfile:1.7
# -----------------------------------------------------------------------------
# Next.js 15 standalone build for apps/web. Build context = monorepo root.
# -----------------------------------------------------------------------------

ARG NODE_VERSION=22-alpine

# ---- Stage 1: build ----
# The whole workspace is copied and installed in one shot. pnpm's workspace linking needs
# every referenced package's source present, so a split "copy only some node_modules" scheme
# is fragile and silently omits packages/auth-server-ts's own deps. A single install against
# the full tree is what `make build-web` does natively (see ops/ci/scripts/js-workspace-install.sh).
FROM node:${NODE_VERSION} AS builder

RUN corepack enable && corepack prepare pnpm@9.15.0 --activate

WORKDIR /repo

# Full source (a .dockerignore keeps node_modules/.next out of the context).
COPY . .

# Install apps/web AND every workspace package's own external deps. `--filter "./packages/*"`
# is required — without it, ui-react / api-client-ts / auth-server-ts don't get their own
# dependencies (lucide-react, zod, …) installed.
RUN pnpm install --filter "@jperdior/web..." --filter "./packages/*"

ENV NEXT_TELEMETRY_DISABLED=1
RUN pnpm -C apps/web build

# ---- Stage 2: runtime ----
FROM node:${NODE_VERSION} AS runtime

WORKDIR /app

ENV NODE_ENV=production
ENV NEXT_TELEMETRY_DISABLED=1
ENV PORT=3000
ENV HOSTNAME=0.0.0.0

# `outputFileTracingRoot` in next.config.ts roots the standalone output at /repo.
COPY --from=builder /repo/apps/web/.next/standalone ./
COPY --from=builder /repo/apps/web/.next/static ./apps/web/.next/static
COPY --from=builder /repo/apps/web/public ./apps/web/public

USER node

EXPOSE 3000

CMD ["node", "apps/web/server.js"]
```

**`ops/docker/admin/Dockerfile`** — same rewrite, admin filter/port/entrypoint, and **add**
the previously-missing public COPY:

```dockerfile
# ---- Stage 1: build ----
FROM node:${NODE_VERSION} AS builder
RUN corepack enable && corepack prepare pnpm@9.15.0 --activate
WORKDIR /repo
COPY . .
RUN pnpm install --filter "@jperdior/admin..." --filter "./packages/*"
ENV NEXT_TELEMETRY_DISABLED=1
RUN pnpm -C apps/admin build

# ---- Stage 2: runtime ----
FROM node:${NODE_VERSION} AS runtime
WORKDIR /app
ENV NODE_ENV=production NEXT_TELEMETRY_DISABLED=1 PORT=3001 HOSTNAME=0.0.0.0
COPY --from=builder /repo/apps/admin/.next/standalone ./
COPY --from=builder /repo/apps/admin/.next/static ./apps/admin/.next/static
COPY --from=builder /repo/apps/admin/public ./apps/admin/public
USER node
EXPOSE 3001
CMD ["node", "apps/admin/server.js"]
```

> **Package-name check during implementation:** confirm the exact workspace names in
> `apps/web/package.json` / `apps/admin/package.json` before writing the `--filter` (spec
> assumes `@jperdior/web` / `@jperdior/admin`, matching `transpilePackages`). Adjust if the
> manifests differ.

**Verification (once, at the end of this phase — not per-phase):**
- `docker build -f ops/docker/web/Dockerfile -t jperdior-web:verify .`
- `docker build -f ops/docker/admin/Dockerfile -t jperdior-admin:verify .`
- Both build to completion; the runtime layer includes `apps/{web,admin}/public`.
- Requires Docker + network for base images.

---

## Phasing

| Phase | Contents | End-state gate |
|-------|----------|----------------|
| 1 | JWT key perms (`_helpers.tpl`, api/worker `fsGroup`) | `helm template ops/k8s` renders; grep confirms `0440` + `fsGroup: 33` |
| 2 | Dockerfile rewrite (web+admin), `public/.gitkeep` ×2, `.dockerignore`, admin public COPY, `docs/ops.md` note | `make lint` + `make build-web` green; **one-time** `docker build` of both images succeeds |

Single PR ships both phases + the spec.

## Risks & Impact

| Risk | Sev | Mitigation | Residual |
|------|-----|-----------|----------|
| `fsGroup` change triggers a pod restart on next deploy | Low | Expected rolling restart; no data touched | None |
| `COPY . .` enlarges build context if `.dockerignore` is wrong → slow build / secret leak | Med | Add/verify `.dockerignore` (node_modules, .next, .git, .claude, api var) in same phase | Low |
| Single-install build is slower to cache than the old manifest-first layering | Low | Matches `make build-web`; acceptable for correctness | None |
| `docker build` verification needs network/base images unavailable in some sandboxes | Low | If Docker unavailable, fall back to `helm template` + `make build-web` and note it | Doc-only |

## Integration Coverage

Ops-only — no PHPUnit/Vitest changes. Verification is the phase gates above:
`helm template`, `make lint`, `make build-web`, and the one-time `docker build` of both
images.

## Docs to sync

- `docs/ops.md` — note that `apps/{web,admin}/public/` is served in prod images and that the
  JWT private key is mounted group-readable (`0440` + `fsGroup: 33`).
- No context `AGENTS.md` under `apps/api/src/*` is touched (no bounded context changes).

## Changelog

- _(to be filled after implementation)_
