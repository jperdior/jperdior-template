# apps/web — Agents Guidelines

Next.js 15 public app (App Router, RSC by default). Consumes the API via `@jperdior/api-client-ts/server`. Built on `@jperdior/ui-react` + Tailwind + shadcn primitives.

## Always

- Server Components by default. Mark `'use client'` only when a file needs interactivity, browser APIs, state, or event handlers.
- Use `apiClient()` from `@jperdior/api-client-ts/server` in Server Components and Server Actions. It reads tokens from cookies and auto-refreshes.
- Use Server Actions for mutations (`'use server'` files). Wrap with `useActionState` on the client form.
- Forms: `react-hook-form` + `zod` schemas. Always support `Cmd/Ctrl+Enter` submit + `Escape` cancel.
- Loading + error states are mandatory (`loading.tsx`, `error.tsx` per route).
- Use DS tokens; never hardcoded shades. See `.ai/ds-rules.md`.

## Never

- **Never** use raw `fetch` in app code. Use `apiClient()` from `@jperdior/api-client-ts/server`.
- **Never** store tokens in `localStorage`. They're HTTP-only cookies, written by `persistTokens()`.
- **Never** import server-only code (`next/headers`, `@jperdior/api-client-ts/server`) inside `'use client'` files. TypeScript will warn but check.
- **Never** hard-code colors / sizes — use the DS preset tokens.
- **Never** put e2e tests anywhere other than `e2e/`.

## Validation Commands

```bash
pnpm -C apps/web typecheck
pnpm -C apps/web lint
pnpm -C apps/web build
pnpm -C apps/web test:e2e     # requires `make start` to be running
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
│       └── notes/
│           ├── page.tsx             ← list
│           ├── actions.ts           ← create/update/delete server actions
│           ├── new/{page,NewNoteForm}.tsx
│           └── [id]/{page,EditNoteForm}.tsx
├── lib/auth.ts                      ← persistTokens / clearTokens / isAuthenticated
└── middleware.ts                    ← redirect to /login when no cookies
e2e/
├── auth.spec.ts
├── helpers/auth.ts
playwright.config.ts
tailwind.config.ts
next.config.ts
```

## Cookie Strategy

- Access token  → cookie `at` (HttpOnly, SameSite=Lax)
- Refresh token → cookie `rt` (HttpOnly, SameSite=Lax)
- `apiClient()` from the server entry auto-refreshes on 401 by hitting `/auth/refresh` with the `rt` cookie value, persisting the new pair via `cookies().set(...)`.
- Sign-out clears both cookies (Server Action on the layout).

## Adding a New Route

Run `/scaffold-nextjs-page` and follow the prompts. See `apps/web/AGENTS.md` plus `.ai/skills/scaffold-nextjs-page/SKILL.md`.
