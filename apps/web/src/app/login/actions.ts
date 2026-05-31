'use server';

import { redirect } from 'next/navigation';
import { z } from 'zod';
import { createApiClient } from '@jperdior/api-client-ts';
import { persistTokens } from '@/lib/auth';

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
    next:     formData.get('next') ?? '/dashboard',
  });
  if (!parsed.success) return { error: 'Invalid email or password.' };

  const client = createApiClient({ baseUrl: process.env.INTERNAL_API_URL ?? 'http://api:8080' });

  let token: string;
  let refresh_token: string;
  try {
    ({ token, refresh_token } = await client.login({ email: parsed.data.email, password: parsed.data.password }));
  } catch {
    return { error: 'Invalid credentials.' };
  }

  await persistTokens(token, refresh_token);

  const authedClient = createApiClient({
    baseUrl: process.env.INTERNAL_API_URL ?? 'http://api:8080',
    getAccessToken: () => token,
  });
  try {
    const me = await authedClient.me();
    if (me.mustResetPassword) redirect('/reset-password');
  } catch {
    // If me() fails, proceed to normal redirect — don't block login
  }

  redirect(parsed.data.next || '/dashboard');
}
