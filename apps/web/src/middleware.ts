import createMiddleware from 'next-intl/middleware';
import type { NextRequest } from 'next/server';
import { createAuthMiddleware } from '@jperdior/auth-server';
import { routing } from './i18n/routing';

const handleI18n = createMiddleware(routing);

// The auth guard runs on the raw (locale-prefixed) pathname, so every public path needs
// its `/es` variant too. English is served without a prefix (`as-needed`), so the bare
// paths cover the default locale. The trailing slash on `reset-password/` whitelists
// token-bearing reset URLs without exposing the authenticated `/reset-password` page.
const auth = createAuthMiddleware({
  publicPaths: [
    '/',
    '/login',
    '/signup',
    '/forgot-password',
    '/es',
    '/es/login',
    '/es/signup',
    '/es/forgot-password',
  ],
  publicPrefixes: ['/reset-password/', '/es/reset-password/'],
});

export function middleware(request: NextRequest) {
  // 1. Guard first: an unauthenticated hit on a protected page short-circuits to /login.
  const authResponse = auth(request);
  if (authResponse.headers.has('location')) {
    return authResponse;
  }

  // 2. Otherwise let next-intl own the response — it resolves the locale, rewrites to the
  //    [locale] segment, and sets the NEXT_LOCALE cookie + hreflang header.
  return handleI18n(request);
}

export const config = {
  // Skip Next internals, API routes, and any path with a file extension.
  matcher: ['/((?!api|_next|_vercel|.*\\..*).*)'],
};
