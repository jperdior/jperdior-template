import { redirect } from 'next/navigation';
import { z } from 'zod';
import { createApiClient, UnauthorizedError, type CurrentUser } from '@jperdior/api-client-ts';
import { API_BASE_URL } from '@jperdior/api-client-ts/server';
import { clearTokens, persistTokens } from './cookies';

const schema = z.object({
  email: z.string().email(),
  password: z.string().min(8),
  next: z.string().optional(),
});

export type SignInState = { error?: string };

export interface SignInConfig {
  /**
   * Runs after login and me(), BEFORE any cookie is persisted. Return true to allow,
   * or an error message to reject — rejection also clears any pre-existing session
   * cookies (a failed re-login on a shared browser must not leave a live session).
   */
  authorize?: (me: CurrentUser) => true | string;
  /**
   * Overrides the destination; receives the me() result and the sanitised next.
   * me is null when me() failed and no authorize is configured (best-effort path).
   */
  postSignInRedirect?: (me: CurrentUser | null, next: string) => string;
  /** Default '/dashboard'. */
  defaultRedirect?: string;
}

/** Only same-origin relative paths are honoured; '//' is protocol-relative, i.e. absolute. */
function sanitizeNext(next: string | undefined, fallback: string): string {
  if (!next || !next.startsWith('/') || next.startsWith('//')) {
    return fallback;
  }

  return next;
}

export function createSignInAction(config: SignInConfig = {}) {
  const defaultRedirect = config.defaultRedirect ?? '/dashboard';

  return async function signIn(_prev: SignInState, formData: FormData): Promise<SignInState> {
    const parsed = schema.safeParse({
      email: formData.get('email'),
      password: formData.get('password'),
      next: formData.get('next') ?? undefined,
    });
    if (!parsed.success) return { error: 'Invalid email or password.' };

    const next = sanitizeNext(parsed.data.next, defaultRedirect);

    let token: string;
    let refreshToken: string;
    try {
      const auth = await createApiClient({ baseUrl: API_BASE_URL }).login({
        email: parsed.data.email,
        password: parsed.data.password,
      });
      token = auth.token;
      refreshToken = auth.refresh_token;
    } catch (error) {
      // A wrong password (401) is expected; anything else (500, network, DNS) must be
      // visible in the server log — the user only ever sees the generic message.
      if (!(error instanceof UnauthorizedError)) {
        console.error('signIn: login request failed:', error);
      }
      return { error: 'Invalid credentials.' };
    }

    // Best-effort when no authorize is configured: a me() hiccup must not block sign-in.
    let me: CurrentUser | null = null;
    try {
      // apiClient() can't serve this call: it reads the cookie jar, and the fresh token
      // is deliberately not persisted yet (authorize runs first).
      me = await createApiClient({ baseUrl: API_BASE_URL, getAccessToken: () => token }).me();
    } catch (error) {
      console.error('signIn: me() request failed:', error);
      me = null;
    }

    if (config.authorize) {
      if (null === me) {
        await clearTokens();
        return { error: 'Could not verify account.' };
      }
      const verdict = config.authorize(me);
      if (true !== verdict) {
        await clearTokens();
        return { error: verdict };
      }
    }

    await persistTokens(token, refreshToken);
    redirect(config.postSignInRedirect?.(me, next) ?? next);

    return {};
  };
}
