import { cookies } from 'next/headers';
import { createApiClient, type ApiClient } from './apiClient';

const REFRESH_COOKIE = 'rt';
const ACCESS_COOKIE  = 'at';

const baseUrl = process.env.INTERNAL_API_URL ?? process.env.NEXT_PUBLIC_API_URL ?? 'http://api:8080';

/**
 * Server-side ApiClient for Next.js Server Components and Server Actions.
 *
 * Reads the access token from a cookie. On 401 attempts to refresh via the refresh-token
 * cookie. Persists the new pair via Server-Action cookie writes (call sites are expected to
 * propagate the new cookies through the `Set-Cookie` response header in Server Actions).
 */
export function apiClient(): ApiClient {
  return createApiClient({
    baseUrl,
    async getAccessToken() {
      const jar = await cookies();
      return jar.get(ACCESS_COOKIE)?.value ?? null;
    },
    async refresh() {
      const jar = await cookies();
      const rt  = jar.get(REFRESH_COOKIE)?.value;
      if (!rt) return null;

      const res = await fetch(`${baseUrl}/auth/refresh`, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ refresh_token: rt }),
      });
      if (!res.ok) return null;
      const payload = await res.json() as { token: string; refresh_token: string };
      jar.set(ACCESS_COOKIE,  payload.token,         { httpOnly: true, sameSite: 'lax', secure: process.env.NODE_ENV === 'production', path: '/' });
      jar.set(REFRESH_COOKIE, payload.refresh_token, { httpOnly: true, sameSite: 'lax', secure: process.env.NODE_ENV === 'production', path: '/' });
      return payload.token;
    },
  });
}

export const ACCESS_TOKEN_COOKIE  = ACCESS_COOKIE;
export const REFRESH_TOKEN_COOKIE = REFRESH_COOKIE;

export type { ApiClient, ApiClientConfig, Note, CurrentUser, UserSummary } from './apiClient';
