# syntax=docker/dockerfile:1.7
# -----------------------------------------------------------------------------
# Next.js 15 standalone build for apps/admin. Same shape as apps/web — different
# entrypoint + default PORT. Build context = monorepo root.
# -----------------------------------------------------------------------------

ARG NODE_VERSION=22-alpine

FROM node:${NODE_VERSION} AS deps

RUN corepack enable && corepack prepare pnpm@9.15.0 --activate

WORKDIR /repo

COPY package.json pnpm-workspace.yaml turbo.json ./
COPY apps/web/package.json ./apps/web/
COPY apps/admin/package.json ./apps/admin/
COPY packages/ui-react/package.json ./packages/ui-react/
COPY packages/api-client-ts/package.json ./packages/api-client-ts/

RUN pnpm install --filter "@jperdior/admin..."

FROM node:${NODE_VERSION} AS builder

RUN corepack enable && corepack prepare pnpm@9.15.0 --activate

WORKDIR /repo
COPY --from=deps /repo/node_modules ./node_modules
COPY --from=deps /repo/apps/admin/node_modules ./apps/admin/node_modules
COPY --from=deps /repo/packages/ui-react/node_modules ./packages/ui-react/node_modules
COPY --from=deps /repo/packages/api-client-ts/node_modules ./packages/api-client-ts/node_modules

COPY apps/admin ./apps/admin
COPY packages/ui-react ./packages/ui-react
COPY packages/api-client-ts ./packages/api-client-ts
COPY package.json pnpm-workspace.yaml turbo.json ./

ENV NEXT_TELEMETRY_DISABLED=1
RUN pnpm -C apps/admin build

FROM node:${NODE_VERSION} AS runtime

WORKDIR /app

ENV NODE_ENV=production
ENV NEXT_TELEMETRY_DISABLED=1
ENV PORT=3001
ENV HOSTNAME=0.0.0.0

COPY --from=builder /repo/apps/admin/.next/standalone ./
COPY --from=builder /repo/apps/admin/.next/static ./apps/admin/.next/static

USER node

EXPOSE 3001

CMD ["node", "apps/admin/server.js"]
