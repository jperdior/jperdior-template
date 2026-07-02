import { createAuthMiddleware } from '@jperdior/auth-server';

// The trailing slash on `/reset-password/` is intentional: it whitelists token-bearing
// reset URLs (`/reset-password/<token>`) without exposing the existing authenticated
// `/reset-password` page (used by `mustResetPassword` flow).
export const middleware = createAuthMiddleware({
  publicPaths: ['/', '/login', '/signup', '/forgot-password'],
  publicPrefixes: ['/reset-password/'],
});

export const config = {
  matcher: ['/((?!_next/static|_next/image|favicon.ico).*)'],
};
