'use server';

import { redirect } from 'next/navigation';
import { z } from 'zod';
import { createApiClient } from '@jperdior/api-client-ts';
import { clearTokens, persistTokens } from '@/lib/auth';

const schema = z.object({
  email:    z.string().email(),
  password: z.string().min(8),
  next:     z.string().optional(),
});

export type LoginState = { error?: string };

export async function loginAction(_prev: LoginState, formData: FormData): Promise<LoginState> {
  const parsed = schema.safeParse({
    email:    formData.get('email'),
    password: formData.get('password'),
    next:     formData.get('next') ?? '/users',
  });
  if (!parsed.success) return { error: 'Invalid email or password.' };

  const baseUrl = process.env.INTERNAL_API_URL ?? 'http://api:8080';
  const client  = createApiClient({ baseUrl });

  let token: string;
  let refreshToken: string;
  try {
    const auth = await client.login({ email: parsed.data.email, password: parsed.data.password });
    token        = auth.token;
    refreshToken = auth.refresh_token;
  } catch {
    return { error: 'Invalid credentials.' };
  }

  // Enforce admin-only access before persisting cookies.
  const authedClient = createApiClient({
    baseUrl,
    getAccessToken: () => token,
  });
  let isAdmin = false;
  try {
    const me = await authedClient.me();
    isAdmin = me.roles.includes('ROLE_ADMIN');
  } catch {
    return { error: 'Could not verify account.' };
  }

  if (!isAdmin) {
    await clearTokens();
    return { error: 'This account does not have admin access.' };
  }

  await persistTokens(token, refreshToken);
  redirect(parsed.data.next || '/users');
}
