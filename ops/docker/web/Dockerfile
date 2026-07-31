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

# Full source (a .dockerignore keeps node_modules/.next and secrets out of the context).
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
