# @jperdior/api-client-ts — Agents Guidelines

Thin, typed HTTP client for the Symfony API. Two entry points:
- `@jperdior/api-client-ts` — base factory (`createApiClient`) for any environment.
- `@jperdior/api-client-ts/server` — Next.js-aware wrapper that reads tokens from cookies and auto-refreshes.
- `@jperdior/api-client-ts/types` — generated OpenAPI types from `apps/api/openapi.json`.

## Always

- Regenerate `src/types.gen.ts` after any API contract change:
  ```
  make gen-api
  ```
- Keep handwritten code in `src/apiClient.ts` lean — one method per endpoint, no business logic.
- Throw typed errors (`UnauthorizedError`, `ConflictError`, `ValidationError`) so consumers can `catch` by class.
- Auto-refresh on 401 exactly once per request — never loop.

## Never

- Never edit `src/types.gen.ts` by hand. CI fails the PR if it's out of date.
- Never bundle React, Next.js, or any UI framework here. Pure fetch.
- Never store tokens in `localStorage` — refresh-token must be `HttpOnly` cookie (the server entry does this).
- Never log tokens.

## Structure

```
src/
├── index.ts           ← public client + types
├── apiClient.ts       ← createApiClient factory
├── errors.ts          ← ApiError + subclasses
├── server.ts          ← Next.js Server Component / Server Action variant (cookies + refresh)
└── types.gen.ts       ← generated from OpenAPI; do NOT edit
```

## Validation

```bash
pnpm -C packages/api-client-ts typecheck
pnpm -C packages/api-client-ts gen     # regenerate from running API
```
