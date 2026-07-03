import { describe, it, expect, vi, beforeEach } from 'vitest';

const jar = new Map<string, { value: string; options?: Record<string, unknown> }>();
const setSpy = vi.fn((name: string, value: string, options?: Record<string, unknown>) => {
  jar.set(name, { value, options });
});
const deleteSpy = vi.fn((name: string) => {
  jar.delete(name);
});

vi.mock('next/headers', () => ({
  cookies: async () => ({
    get: (n: string) => (jar.has(n) ? { name: n, value: jar.get(n)?.value ?? '' } : undefined),
    has: (n: string) => jar.has(n),
    set: setSpy,
    delete: deleteSpy,
  }),
}));

const redirectSpy = vi.fn();
vi.mock('next/navigation', () => ({
  redirect: (url: string) => redirectSpy(url),
}));

const login = vi.fn();
const me = vi.fn();
vi.mock('@jperdior/api-client-ts', async (importOriginal) => {
  const actual = await importOriginal<typeof import('@jperdior/api-client-ts')>();
  return { ...actual, createApiClient: () => ({ login, me }) };
});

import { createSignInAction, createSignOutAction } from '../index';

const aUser = {
  id: 'u-1',
  email: 'jane@example.com',
  roles: ['ROLE_USER'],
  createdAt: '2026-07-02T10:00:00+00:00',
  mustResetPassword: false,
};

function fd(entries: Record<string, string>): FormData {
  const data = new FormData();
  for (const [k, v] of Object.entries(entries)) data.set(k, v);
  return data;
}

beforeEach(() => {
  jar.clear();
  vi.clearAllMocks();
  login.mockResolvedValue({ token: 'T-access', refresh_token: 'T-refresh' });
  me.mockResolvedValue(aUser);
});

describe('createSignInAction', () => {
  it('rejects malformed input without calling the API', async () => {
    const signIn = createSignInAction();

    const state = await signIn({}, fd({ email: 'nope', password: 'pw' }));

    expect(state).toEqual({ error: 'Invalid email or password.' });
    expect(login).not.toHaveBeenCalled();
  });

  it('returns an error on invalid credentials and persists nothing', async () => {
    login.mockRejectedValue(new Error('401'));
    const signIn = createSignInAction();

    const state = await signIn({}, fd({ email: 'jane@example.com', password: 'password123' }));

    expect(state).toEqual({ error: 'Invalid credentials.' });
    expect(setSpy).not.toHaveBeenCalled();
  });

  it('persists both cookies with the security attributes and redirects to the default', async () => {
    const signIn = createSignInAction();

    await signIn({}, fd({ email: 'jane@example.com', password: 'password123' }));

    expect(setSpy).toHaveBeenCalledWith('at', 'T-access', {
      httpOnly: true, sameSite: 'lax', secure: false, path: '/',
    });
    expect(setSpy).toHaveBeenCalledWith('rt', 'T-refresh', {
      httpOnly: true, sameSite: 'lax', secure: false, path: '/',
    });
    expect(redirectSpy).toHaveBeenCalledWith('/dashboard');
  });

  it('honours relative next paths', async () => {
    const signIn = createSignInAction();

    await signIn({}, fd({ email: 'jane@example.com', password: 'password123', next: '/settings' }));
    expect(redirectSpy).toHaveBeenLastCalledWith('/settings');

    await signIn({}, fd({ email: 'jane@example.com', password: 'password123', next: '/x' }));
    expect(redirectSpy).toHaveBeenLastCalledWith('/x');
  });

  it('rejects absolute and protocol-relative next values', async () => {
    const signIn = createSignInAction();

    await signIn({}, fd({ email: 'jane@example.com', password: 'password123', next: 'https://evil.tld' }));
    expect(redirectSpy).toHaveBeenLastCalledWith('/dashboard');

    await signIn({}, fd({ email: 'jane@example.com', password: 'password123', next: '//evil.tld' }));
    expect(redirectSpy).toHaveBeenLastCalledWith('/dashboard');
  });

  it('applies the postSignInRedirect rule', async () => {
    me.mockResolvedValue({ ...aUser, mustResetPassword: true });
    const signIn = createSignInAction({
      postSignInRedirect: (user, next) => (user?.mustResetPassword ? '/reset-password' : next),
    });

    await signIn({}, fd({ email: 'jane@example.com', password: 'password123', next: '/settings' }));

    expect(redirectSpy).toHaveBeenCalledWith('/reset-password');
  });

  it('authorize rejection returns the error, persists nothing, and clears pre-existing cookies', async () => {
    jar.set('at', { value: 'stale-access' });
    jar.set('rt', { value: 'stale-refresh' });
    const signIn = createSignInAction({
      authorize: (user) => user.roles.includes('ROLE_ADMIN') || 'This account does not have admin access.',
    });

    const state = await signIn({}, fd({ email: 'jane@example.com', password: 'password123' }));

    expect(state).toEqual({ error: 'This account does not have admin access.' });
    expect(setSpy).not.toHaveBeenCalled();
    expect(deleteSpy).toHaveBeenCalledWith('at');
    expect(deleteSpy).toHaveBeenCalledWith('rt');
    expect(jar.size).toBe(0);
    expect(redirectSpy).not.toHaveBeenCalled();
  });

  it('me() failure with authorize configured blocks sign-in', async () => {
    me.mockRejectedValue(new Error('boom'));
    const signIn = createSignInAction({ authorize: () => true });

    const state = await signIn({}, fd({ email: 'jane@example.com', password: 'password123' }));

    expect(state).toEqual({ error: 'Could not verify account.' });
    expect(setSpy).not.toHaveBeenCalled();
  });

  it('me() failure without authorize still signs in (best-effort tolerance)', async () => {
    me.mockRejectedValue(new Error('boom'));
    const signIn = createSignInAction();

    await signIn({}, fd({ email: 'jane@example.com', password: 'password123' }));

    expect(setSpy).toHaveBeenCalledWith('at', 'T-access', expect.anything());
    expect(redirectSpy).toHaveBeenCalledWith('/dashboard');
  });
});

describe('createSignOutAction', () => {
  it('clears both cookies and redirects to the login page', async () => {
    jar.set('at', { value: 'x' });
    jar.set('rt', { value: 'y' });
    const signOut = createSignOutAction();

    await signOut();

    expect(jar.size).toBe(0);
    expect(redirectSpy).toHaveBeenCalledWith('/login');
  });
});
