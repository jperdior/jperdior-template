import { NextResponse, type NextRequest } from 'next/server';

// Cookie names mirror ACCESS_TOKEN_COOKIE / REFRESH_TOKEN_COOKIE from
// @jperdior/api-client-ts/server (the canonical definition). They are literal here so the
// proxy bundle never imports next/headers; proxy.test.ts locks the parity.
const ACCESS_COOKIE = 'at';
const REFRESH_COOKIE = 'rt';

export interface AuthProxyConfig {
  /** Exact pathnames that never require a session. */
  publicPaths: string[];
  /** Pathname prefixes that never require a session (e.g. token-bearing reset links). */
  publicPrefixes?: string[];
  /** Default '/login'. */
  loginPath?: string;
}

export function createAuthProxy(config: AuthProxyConfig) {
  const loginPath = config.loginPath ?? '/login';
  const publicPrefixes = config.publicPrefixes ?? [];

  return function proxy(req: NextRequest): NextResponse {
    const { pathname } = req.nextUrl;

    if (
      config.publicPaths.includes(pathname)
      || pathname.startsWith('/_next')
      || pathname.startsWith('/api')
      || publicPrefixes.some((prefix) => pathname.startsWith(prefix))
    ) {
      return NextResponse.next();
    }

    if (!req.cookies.has(ACCESS_COOKIE) && !req.cookies.has(REFRESH_COOKIE)) {
      const url = req.nextUrl.clone();
      url.pathname = loginPath;
      // Drop the protected page's own query params from the login URL, but keep them
      // inside `next` so the post-login redirect restores the full destination.
      url.search = '';
      url.searchParams.set('next', pathname + req.nextUrl.search);

      return NextResponse.redirect(url);
    }

    return NextResponse.next();
  };
}
