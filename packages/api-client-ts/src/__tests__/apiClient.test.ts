import { afterEach, describe, expect, it, vi } from 'vitest';
import { createApiClient } from '../apiClient';
import { UnauthorizedError } from '../errors';

function jsonResponse(status: number, body: unknown): Response {
  return new Response(JSON.stringify(body), { status, headers: { 'Content-Type': 'application/json' } });
}

afterEach(() => {
  vi.unstubAllGlobals();
});

describe('request — dead sessions (expired access token, failed refresh)', () => {
  it('signals onUnauthorized and retries once anonymously', async () => {
    // The stale-browser case: expired access token, revoked (single-use)
    // refresh token. Endpoints that allow anonymous access must degrade to
    // logged-out content instead of crashing the page render.
    const calls: Array<string | null> = [];
    vi.stubGlobal('fetch', vi.fn(async (_url: URL | string, init?: RequestInit) => {
      const auth = (init?.headers as Record<string, string>)?.Authorization ?? null;
      calls.push(auth);
      if (auth) return jsonResponse(401, { code: 401, message: 'Expired JWT Token' });
      return jsonResponse(200, { anonymous: true });
    }));
    const onUnauthorized = vi.fn();

    const client = createApiClient({
      baseUrl: 'http://api.test',
      getAccessToken: () => 'expired-token',
      refresh: async () => null,
      onUnauthorized,
    });

    const result = await client.me();

    expect(result).toEqual({ anonymous: true });
    expect(onUnauthorized).toHaveBeenCalledOnce();
    expect(calls).toEqual(['Bearer expired-token', null]);
  });

  it('still throws UnauthorizedError when the anonymous retry also 401s (protected endpoint)', async () => {
    vi.stubGlobal('fetch', vi.fn(async () => jsonResponse(401, { code: 401, message: 'Expired JWT Token' })));

    const client = createApiClient({
      baseUrl: 'http://api.test',
      getAccessToken: () => 'expired-token',
      refresh: async () => null,
    });

    await expect(client.me()).rejects.toBeInstanceOf(UnauthorizedError);
  });

  it('uses the refreshed token when refresh succeeds — no anonymous fallback, no signal', async () => {
    const calls: Array<string | null> = [];
    vi.stubGlobal('fetch', vi.fn(async (_url: URL | string, init?: RequestInit) => {
      const auth = (init?.headers as Record<string, string>)?.Authorization ?? null;
      calls.push(auth);
      if (auth === 'Bearer fresh-token') return jsonResponse(200, { id: 'u1' });
      return jsonResponse(401, { code: 401, message: 'Expired JWT Token' });
    }));
    const onUnauthorized = vi.fn();

    const client = createApiClient({
      baseUrl: 'http://api.test',
      getAccessToken: () => 'expired-token',
      refresh: async () => 'fresh-token',
      onUnauthorized,
    });

    await client.me();

    expect(calls).toEqual(['Bearer expired-token', 'Bearer fresh-token']);
    expect(onUnauthorized).not.toHaveBeenCalled();
  });

  it('does not attempt refresh or fallback for requests that never carried a token', async () => {
    const fetchMock = vi.fn(async () => jsonResponse(401, { code: 401, message: 'Bad credentials' }));
    vi.stubGlobal('fetch', fetchMock);
    const refresh = vi.fn(async () => 'fresh-token');

    const client = createApiClient({ baseUrl: 'http://api.test', refresh });

    await expect(client.me()).rejects.toBeInstanceOf(UnauthorizedError);
    expect(refresh).not.toHaveBeenCalled();
    expect(fetchMock).toHaveBeenCalledTimes(1);
  });
});
