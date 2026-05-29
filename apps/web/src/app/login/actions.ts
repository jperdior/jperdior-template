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
    next:     formData.get('next') ?? '/notes',
  });
  if (!parsed.success) return { error: 'Invalid email or password.' };

  const client = createApiClient({ baseUrl: process.env.INTERNAL_API_URL ?? 'http://api:8080' });
  try {
    const { token, refresh_token } = await client.login({ email: parsed.data.email, password: parsed.data.password });
    await persistTokens(token, refresh_token);
  } catch {
    return { error: 'Invalid credentials.' };
  }

  redirect(parsed.data.next || '/notes');
}
