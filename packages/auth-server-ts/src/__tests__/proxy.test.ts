import { describe, it, expect, vi } from 'vitest';

// The server entry imports next/headers at module level; harmless here, but
// mocked so this suite stays runnable in a plain node environment.
vi.mock('next/headers', () => ({ cookies: async () => new Map() }));

import { NextRequest } from 'next/server';
import { ACCESS_TOKEN_COOKIE, REFRESH_TOKEN_COOKIE } from '@jperdior/api-client-ts/server';
import { createAuthProxy } from '../index';

const proxy = createAuthProxy({
  publicPaths: ['/', '/login'],
  publicPrefixes: ['/reset-password/'],
});

function request(path: string, cookie?: string): NextRequest {
  return new NextRequest(`http://app.local${path}`, cookie ? { headers: { cookie } } : undefined);
}

describe('createAuthProxy', () => {
  it('lets public paths through', () => {
    expect(proxy(request('/login')).headers.get('x-middleware-next')).toBe('1');
  });

  it('lets public prefixes through', () => {
    expect(proxy(request('/reset-password/abc123')).headers.get('x-middleware-next')).toBe('1');
  });

  it('redirects to login with the next param when no cookie is present', () => {
    const res = proxy(request('/dashboard'));

    expect(res.status).toBe(307);
    expect(res.headers.get('location')).toBe('http://app.local/login?next=%2Fdashboard');
  });

  it('preserves the original query string inside next without leaking it onto the login URL', () => {
    const res = proxy(request('/dashboard?tab=billing&page=2'));

    expect(res.status).toBe(307);
    expect(res.headers.get('location')).toBe(
      'http://app.local/login?next=%2Fdashboard%3Ftab%3Dbilling%26page%3D2',
    );
  });

  it('lets requests through with the access-token cookie (name parity with api-client)', () => {
    const res = proxy(request('/dashboard', `${ACCESS_TOKEN_COOKIE}=tok`));

    expect(res.headers.get('x-middleware-next')).toBe('1');
  });

  it('lets requests through with only the refresh-token cookie', () => {
    const res = proxy(request('/dashboard', `${REFRESH_TOKEN_COOKIE}=tok`));

    expect(res.headers.get('x-middleware-next')).toBe('1');
  });
});
