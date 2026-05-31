# syntax=docker/dockerfile:1.7
# -----------------------------------------------------------------------------
# Next.js 15 standalone build for apps/web. Build context = monorepo root.
# -----------------------------------------------------------------------------

ARG NODE_VERSION=22-alpine

# ---- Stage 1: workspace install ----
FROM node:${NODE_VERSION} AS deps

RUN corepack enable && corepack prepare pnpm@9.15.0 --activate

WORKDIR /repo

# Manifests first for cache.
COPY package.json pnpm-workspace.yaml turbo.json ./
COPY apps/web/package.json ./apps/web/
COPY apps/admin/package.json ./apps/admin/
COPY packages/ui-react/package.json ./packages/ui-react/
COPY packages/api-client-ts/package.json ./packages/api-client-ts/

RUN pnpm install --filter "./apps/web..."

# ---- Stage 2: build ----
FROM node:${NODE_VERSION} AS builder

RUN corepack enable && corepack prepare pnpm@9.15.0 --activate

WORKDIR /repo
COPY --from=deps /repo/node_modules ./node_modules
COPY --from=deps /repo/apps/web/node_modules ./apps/web/node_modules
COPY --from=deps /repo/packages/ui-react/node_modules ./packages/ui-react/node_modules
COPY --from=deps /repo/packages/api-client-ts/node_modules ./packages/api-client-ts/node_modules

# Now copy the source tree.
COPY apps/web ./apps/web
COPY packages/ui-react ./packages/ui-react
COPY packages/api-client-ts ./packages/api-client-ts
COPY package.json pnpm-workspace.yaml turbo.json ./

ENV NEXT_TELEMETRY_DISABLED=1
RUN pnpm -C apps/web build

# ---- Stage 3: runtime ----
FROM node:${NODE_VERSION} AS runtime

WORKDIR /app

ENV NODE_ENV=production
ENV NEXT_TELEMETRY_DISABLED=1
ENV PORT=3000
ENV HOSTNAME=0.0.0.0

# `outputFileTracingRoot` set in next.config.ts puts the standalone output rooted at /repo.
COPY --from=builder /repo/apps/web/.next/standalone ./
COPY --from=builder /repo/apps/web/.next/static ./apps/web/.next/static
COPY --from=builder /repo/apps/web/public ./apps/web/public

USER node

EXPOSE 3000

CMD ["node", "apps/web/server.js"]
