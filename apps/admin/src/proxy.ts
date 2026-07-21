import { createAuthProxy } from '@jperdior/auth-server';

export const proxy = createAuthProxy({
  publicPaths: ['/', '/login'],
});

export const config = {
  matcher: ['/((?!_next/static|_next/image|favicon.ico).*)'],
};
