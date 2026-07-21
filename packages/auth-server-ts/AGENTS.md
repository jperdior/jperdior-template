# @jperdior/auth-server — Agents Guidelines

The session module for the Next.js apps. Everything a page, action, or proxy needs to
know about sign-in, sign-out, route guarding, and token cookies lives behind this package's
interface — apps configure it, they never hand-roll token handling.

## Interface

```ts
createSignInAction({ authorize?, postSignInRedirect?, defaultRedirect? })
createSignOutAction({ redirectTo? })
createAuthProxy({ publicPaths, publicPrefixes?, loginPath? })
persistTokens(token, refreshToken) / clearTokens() / isAuthenticated()
type SignInState = { error?: string }
```

- `authorize(me)` runs after login + `me()` and **before any cookie is persisted**; returning
  an error string rejects the sign-in and also clears pre-existing session cookies.
- `postSignInRedirect(me, next)` decides the destination (e.g. web's `mustResetPassword` rule);
  `next` is already sanitised.
- The `next` redirect param is sanitised: only relative paths starting with `/` (and not `//`)
  are honoured; anything else falls back to `defaultRedirect`.
- Cookie names (`at`/`rt`) are canonical in `@jperdior/api-client-ts/server`
  (`ACCESS_TOKEN_COOKIE`/`REFRESH_TOKEN_COOKIE`). `proxy.ts` mirrors them as literals so
  the proxy bundle never imports `next/headers` — `proxy.test.ts` locks the parity.

## Always

- Consume this package from app adapters only: a `'use server'` `actions.ts` that wraps the
  factory product in an exported async function, and a `proxy.ts` that exports the factory
  product plus a static `config.matcher`.
- Keep cookie attributes `httpOnly`, `sameSite: 'lax'`, `secure` (prod), `path: '/'` — locked
  by `signIn.test.ts`.

## Never

- Never widen the sign-in flow per app — new behaviour goes here, behind config, with a test.
- Never store tokens outside `HttpOnly` cookies, never log them.
- Never import React or UI code here. Server-side only.

## Validation

```bash
pnpm -C packages/auth-server-ts typecheck
pnpm -C packages/auth-server-ts test     # Vitest, node env, mocked next/headers + api client
```

## Structure

```
src/
├── index.ts        ← public interface
├── cookies.ts      ← persistTokens / clearTokens / isAuthenticated
├── signIn.ts       ← createSignInAction (zod parse → login → me → authorize → persist → redirect)
├── signOut.ts      ← createSignOutAction
├── proxy.ts    ← createAuthProxy (cookie-presence route guard)
└── __tests__/
```
