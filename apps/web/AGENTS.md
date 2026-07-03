# apps/web — Agents Guidelines

Next.js 16 public app (App Router, RSC by default). Consumes the API via `@jperdior/api-client-ts/server`. Built on `@jperdior/ui-react` + Tailwind + shadcn primitives.

## Always

- Server Components by default. Mark `'use client'` only when a file needs interactivity, browser APIs, state, or event handlers.
- Use `apiClient()` from `@jperdior/api-client-ts/server` in Server Components and Server Actions. It reads tokens from cookies and auto-refreshes.
- Use Server Actions for mutations (`'use server'` files). Wrap with `useActionState` on the client form.
- Forms: `react-hook-form` + `zod` schemas. Always support `Cmd/Ctrl+Enter` submit + `Escape` cancel.
- Loading + error states are mandatory (`loading.tsx`, `error.tsx` per route).
- Use DS tokens; never hardcoded shades. See `.ai/ds-rules.md`.

## Never

- **Never** use raw `fetch` in app code. Use `apiClient()` from `@jperdior/api-client-ts/server`.
- **Never** hand-roll session handling. Sign-in/out, route guarding, and token cookies come from `@jperdior/auth-server` (`createSignInAction`, `createSignOutAction`, `createAuthMiddleware`, `persistTokens`/`clearTokens`/`isAuthenticated`).
- **Never** store tokens in `localStorage`. They're HTTP-only cookies, written by `persistTokens()`.
- **Never** import server-only code (`next/headers`, `@jperdior/api-client-ts/server`) inside `'use client'` files. TypeScript will warn but check.
- **Never** hard-code colors / sizes — use the DS preset tokens.
- **Never** put test files outside `src/`. Colocate them next to the unit they cover under `__tests__/`.

## Validation Commands

```bash
pnpm -C apps/web typecheck
pnpm -C apps/web lint
pnpm -C apps/web build
pnpm -C apps/web test         # Vitest + React Testing Library
```

## Structure

```
src/
├── app/
│   ├── layout.tsx                   ← root layout (server)
│   ├── page.tsx                     ← landing
│   ├── loading.tsx                  ← global loading
│   ├── error.tsx                    ← global error (client)
│   ├── not-found.tsx                ← 404
│   ├── globals.css                  ← imports @jperdior/ui-react/styles.css
│   ├── login/{page,LoginForm,actions}.tsx
│   ├── signup/{page,SignUpForm,actions}.tsx
│   └── (app)/                       ← authenticated route group
│       ├── layout.tsx               ← guards via cookies; shows nav + sign-out
│       └── dashboard/page.tsx       ← post-login landing
└── middleware.ts                    ← createAuthMiddleware adapter (public paths + reset-password prefix)
vitest.config.ts                     ← Vitest + jsdom config
vitest.setup.ts                      ← jest-dom matchers + next/link & next/navigation mocks
tailwind.config.ts
next.config.ts
```

## Cookie Strategy

- Access token  → cookie `at` (HttpOnly, SameSite=Lax); Refresh token → cookie `rt` (same). Names are canonical in `@jperdior/api-client-ts/server`; session helpers come from `@jperdior/auth-server`.
- `apiClient()` from the server entry auto-refreshes on 401 by hitting `/auth/refresh` with the `rt` cookie value, persisting the new pair via `cookies().set(...)`.
- **Dead session (expired access + revoked/expired refresh) is handled globally**: `apiClient()` clears the `at`/`rt` cookies and `redirect()`s to `/login?reason=expired`. Pages and Server Actions do **not** handle this themselves — just call `apiClient()`. The one requirement: a `catch` around an `apiClient()` call must `unstable_rethrow(e)` (from `next/navigation`) first, or it will swallow the redirect and turn an expired session into a stray form error.
- Sign-in is `createSignInAction({ postSignInRedirect })` — the web adapter passes the `mustResetPassword → /reset-password` rule. The `next` redirect param only honours relative paths.
- Sign-out clears both cookies (Server Action on the layout, `clearTokens()`).

## Adding a New Route

Run `/scaffold-nextjs-page` and follow the prompts. See `apps/web/AGENTS.md` plus `.ai/skills/scaffold-nextjs-page/SKILL.md`.
