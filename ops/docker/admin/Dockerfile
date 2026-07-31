# syntax=docker/dockerfile:1.7
# -----------------------------------------------------------------------------
# Next.js 15 standalone build for apps/admin. Same shape as apps/web — different
# entrypoint + default PORT. Build context = monorepo root.
# -----------------------------------------------------------------------------

ARG NODE_VERSION=22-alpine

# ---- Stage 1: build ----
# See ops/docker/web/Dockerfile — single full-tree install so every workspace package
# (incl. packages/auth-server-ts) gets its own deps. Mirrors ops/ci/scripts/js-workspace-install.sh.
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

ENV NODE_ENV=production
ENV NEXT_TELEMETRY_DISABLED=1
ENV PORT=3001
ENV HOSTNAME=0.0.0.0

COPY --from=builder /repo/apps/admin/.next/standalone ./
COPY --from=builder /repo/apps/admin/.next/static ./apps/admin/.next/static
COPY --from=builder /repo/apps/admin/public ./apps/admin/public

USER node

EXPOSE 3001

CMD ["node", "apps/admin/server.js"]
