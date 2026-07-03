import { cookies } from 'next/headers';
import { redirect } from 'next/navigation';
import { createApiClient, type ApiClient } from './apiClient';

const REFRESH_COOKIE = 'rt';
const ACCESS_COOKIE  = 'at';

const baseUrl = process.env.INTERNAL_API_URL ?? process.env.NEXT_PUBLIC_API_URL ?? 'http://api:8080';

const cookieOptions = () => ({
  httpOnly: true as const,
  sameSite: 'lax' as const,
  secure: process.env.NODE_ENV === 'production',
  path: '/',
});

/**
 * Best-effort cleanup of a dead session's cookies. Cookie writes are only
 * allowed in Server Actions and Route Handlers — in Server Components this is
 * a no-op (the redirect that follows takes the user to a clean state anyway).
 */
async function clearDeadSessionCookies(): Promise<void> {
  try {
    const jar = await cookies();
    jar.delete(ACCESS_COOKIE);
    jar.delete(REFRESH_COOKIE);
  } catch { /* no-op in Server Components */ }
}

/**
 * The one global rule for a dead session (expired access token + revoked or
 * expired refresh token): drop the stale cookies and send the user to the
 * login page with an explanatory reason. `redirect()` throws a Next.js
 * control-flow signal, so this propagates out of any Server Component render
 * or Server Action and short-circuits the request — no page or action has to
 * handle it individually. The only requirement on call sites is that they must
 * not swallow the signal in a `catch` (use `unstable_rethrow` if they catch).
 */
async function handleDeadSession(): Promise<void> {
  await clearDeadSessionCookies();
  redirect('/login?reason=expired');
}

/**
 * Single-flight refresh. Next.js renders layouts, pages, and parallel segments
 * concurrently, so a single expired access token can trigger several 401s at
 * once. Because the refresh token is single-use (rotated on every use), letting
 * those refreshes run independently means the first one consumes the token and
 * the rest fail against an already-rotated token — tearing down a session that
 * had just been successfully refreshed. Keying an in-flight promise on the
 * refresh-token value collapses concurrent refreshes of the same token into one
 * shared request; every caller reuses its result.
 *
 * This is per-process; it does not coordinate across separate Node instances.
 */
let inFlightRefresh: { rt: string; promise: Promise<string | null> } | null = null;

async function doRefresh(rt: string): Promise<string | null> {
  const res = await fetch(`${baseUrl}/auth/refresh`, {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify({ refresh_token: rt }),
  });
  if (!res.ok) return null;
  const payload = await res.json() as { token: string; refresh_token: string };
  // Cookie writes are only allowed in Server Actions and Route Handlers, not in
  // Server Components. Swallow the error so the refreshed token is still used
  // for this request.
  try {
    const jar = await cookies();
    jar.set(ACCESS_COOKIE,  payload.token,         cookieOptions());
    jar.set(REFRESH_COOKIE, payload.refresh_token, cookieOptions());
  } catch { /* no-op in Server Components */ }
  return payload.token;
}

function refreshSingleFlight(rt: string): Promise<string | null> {
  if (inFlightRefresh && inFlightRefresh.rt === rt) return inFlightRefresh.promise;
  const promise = doRefresh(rt).finally(() => {
    if (inFlightRefresh?.rt === rt) inFlightRefresh = null;
  });
  inFlightRefresh = { rt, promise };
  return promise;
}

/**
 * Server-side ApiClient for Next.js Server Components and Server Actions.
 *
 * Reads the access token from a cookie. On 401 it refreshes via the refresh-token
 * cookie (single-flight, so concurrent renders don't race the single-use token).
 * When the session is dead it redirects to the login page — see `handleDeadSession`.
 */
export function apiClient(): ApiClient {
  return createApiClient({
    baseUrl,
    onUnauthorized: handleDeadSession,
    async getAccessToken() {
      const jar = await cookies();
      return jar.get(ACCESS_COOKIE)?.value ?? null;
    },
    async refresh() {
      const jar = await cookies();
      const rt  = jar.get(REFRESH_COOKIE)?.value;
      if (!rt) return null;
      return refreshSingleFlight(rt);
    },
  });
}

export const ACCESS_TOKEN_COOKIE  = ACCESS_COOKIE;
export const REFRESH_TOKEN_COOKIE = REFRESH_COOKIE;

/** Canonical server-side API base URL — the single source of the env fallback chain. */
export const API_BASE_URL = baseUrl;

export type { ApiClient, ApiClientConfig, CurrentUser, UserSummary } from './apiClient';
