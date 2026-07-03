# apps/admin — Agents Guidelines

Next.js 16 admin back-office. Same stack as `apps/web` (App Router, RSC by default) but **gated to `ROLE_ADMIN`**. Lists every user across the system.

## Always

- Server Components by default. Mark `'use client'` only when a file needs interactivity, browser APIs, state, or event handlers.
- Use `apiClient()` from `@jperdior/api-client-ts/server` in Server Components and Server Actions. It reads tokens from cookies and auto-refreshes.
- Enforce `ROLE_ADMIN` **both** server-side in the API (via `#[IsGranted('ROLE_ADMIN')]`) **and** in the `(admin)` layout (via `apiClient().me()` check). Two gates is correct here — the API is authoritative, the layout is UX.
- Reject non-admin logins *before* persisting cookies — the login adapter passes `authorize: (me) => me.roles.includes('ROLE_ADMIN') || <error>` to `createSignInAction` from `@jperdior/auth-server`; rejection also clears pre-existing session cookies.
- Use DS tokens; never hardcoded shades. See `.ai/ds-rules.md`.

## Never

- **Never** use raw `fetch` in app code. Use `apiClient()`.
- **Never** store tokens in `localStorage`. They're HttpOnly cookies.
- **Never** assume a user is admin without calling `/api/me` — JWT payload is reliable, but the layout/middleware doesn't decode it.
- **Never** import server-only code (`next/headers`, `@jperdior/api-client-ts/server`) inside `'use client'` files.

## Validation Commands

```bash
pnpm -C apps/admin typecheck
pnpm -C apps/admin lint
pnpm -C apps/admin build
pnpm -C apps/admin test         # Vitest + React Testing Library
```

## Structure

```
src/
├── app/
│   ├── layout.tsx                   ← root layout (server)
│   ├── page.tsx                     ← landing (redirects to /users if signed in)
│   ├── loading.tsx                  ← global loading
│   ├── error.tsx                    ← global error (client)
│   ├── not-found.tsx                ← 404
│   ├── globals.css                  ← imports @jperdior/ui-react/styles.css
│   ├── login/{page,LoginForm,actions}.tsx
│   └── (admin)/                     ← gated route group
│       ├── layout.tsx               ← isAuthenticated() + me().roles.includes('ROLE_ADMIN')
│       └── users/page.tsx           ← /api/admin/users
├── components/users/
│   ├── dialogs/                     ← EditRoles / ForceReset / DeleteUser / RestoreUser confirm dialogs;
│   │                                   ConfirmActionDialog owns pending + error + close-on-success,
│   │                                   the named dialogs configure it, callers only pick which one is open
│   ├── UserActionsMenu.tsx          ← list-row menu (3 dialogs)
│   ├── UserDetailActions.tsx        ← detail-page actions (4 dialogs, adds Restore)
│   ├── CreateUserDialog.tsx
│   └── PaginationControls.tsx
└── middleware.ts                    ← createAuthMiddleware adapter (public: / and /login)
```

## Cookie Strategy

Identical to `apps/web` (both come from `@jperdior/auth-server`):
- Access token  → cookie `at` (HttpOnly, SameSite=Lax)
- Refresh token → cookie `rt` (HttpOnly, SameSite=Lax)
- Login refuses to persist cookies if `me().roles` doesn't include `ROLE_ADMIN` (the `authorize` config), and clears any stale session cookies on rejection.
- Sign-out clears both cookies (Server Action on the layout).
- **Dead session is handled globally**: `apiClient()` clears the `at`/`rt` cookies and `redirect()`s to `/login?reason=expired`. Pages and Server Actions don't handle it — but any `catch` around an `apiClient()` call must `unstable_rethrow(e)` (from `next/navigation`) first, or it will swallow the redirect (turning it into a `notFound()`, a bare `/login`, or a form error).

## Differences from `apps/web`

- Public routes are `/` and `/login` only — no signup. Admin accounts are created by promoting an existing user (`make seed-admin EMAIL=…`).
- `(admin)/layout.tsx` performs an extra `me()` call to verify role; the user app has no equivalent.
- All pages call `/api/admin/*` endpoints — these are gated server-side. The admin app is a thin window onto those.
