import { NextResponse, type NextRequest } from 'next/server';

const PUBLIC_PATHS = ['/', '/login', '/signup', '/forgot-password'];

export function middleware(req: NextRequest) {
  const { pathname } = req.nextUrl;

  // The trailing slash on `/reset-password/` is intentional: it whitelists token-bearing
  // reset URLs (`/reset-password/<token>`) without exposing the existing authenticated
  // `/reset-password` page (used by `mustResetPassword` flow).
  if (
    PUBLIC_PATHS.includes(pathname) ||
    pathname.startsWith('/_next') ||
    pathname.startsWith('/api') ||
    pathname.startsWith('/reset-password/')
  ) {
    return NextResponse.next();
  }

  const hasAccess  = req.cookies.has('at');
  const hasRefresh = req.cookies.has('rt');
  if (!hasAccess && !hasRefresh) {
    const url = req.nextUrl.clone();
    url.pathname = '/login';
    url.searchParams.set('next', pathname);
    return NextResponse.redirect(url);
  }

  return NextResponse.next();
}

export const config = {
  matcher: ['/((?!_next/static|_next/image|favicon.ico).*)'],
};
