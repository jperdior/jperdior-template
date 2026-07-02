'use server';

import { redirect } from 'next/navigation';
import { z } from 'zod';
import { apiClient } from '@jperdior/api-client-ts/server';
import { persistTokens } from '@jperdior/auth-server';

const schema = z.object({
  email:    z.string().email(),
  password: z.string().min(8),
});

export type SignUpState = { error?: string };

export async function signUpAction(_prev: SignUpState, formData: FormData): Promise<SignUpState> {
  const parsed = schema.safeParse({
    email:    formData.get('email'),
    password: formData.get('password'),
  });
  if (!parsed.success) return { error: 'Email is invalid or password is too short.' };

  const client = apiClient();

  try {
    await client.signUp(parsed.data);
    const { token, refresh_token } = await client.login(parsed.data);
    await persistTokens(token, refresh_token);
  } catch (error) {
    if (error instanceof Error && error.message.includes('already exists')) {
      return { error: 'An account with this email already exists.' };
    }
    return { error: 'Could not create account.' };
  }

  redirect('/dashboard');
}
