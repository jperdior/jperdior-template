import { cookies } from 'next/headers';
import { ACCESS_TOKEN_COOKIE, REFRESH_TOKEN_COOKIE } from '@jperdior/api-client-ts/server';

const isProd = () => process.env.NODE_ENV === 'production';

export async function persistTokens(token: string, refreshToken: string): Promise<void> {
  const jar = await cookies();
  jar.set(ACCESS_TOKEN_COOKIE, token, {
    httpOnly: true,
    sameSite: 'lax',
    secure: isProd(),
    path: '/',
  });
  jar.set(REFRESH_TOKEN_COOKIE, refreshToken, {
    httpOnly: true,
    sameSite: 'lax',
    secure: isProd(),
    path: '/',
  });
}

export async function clearTokens(): Promise<void> {
  const jar = await cookies();
  jar.delete(ACCESS_TOKEN_COOKIE);
  jar.delete(REFRESH_TOKEN_COOKIE);
}

export async function isAuthenticated(): Promise<boolean> {
  const jar = await cookies();

  return jar.has(ACCESS_TOKEN_COOKIE) || jar.has(REFRESH_TOKEN_COOKIE);
}
