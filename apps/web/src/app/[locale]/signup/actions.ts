'use server';

import { redirect } from 'next/navigation';
import { getTranslations } from 'next-intl/server';
import { z } from 'zod';
import { apiClient } from '@jperdior/api-client-ts/server';
import { persistTokens } from '@jperdior/auth-server';

const schema = z.object({
  email:    z.string().email(),
  password: z.string().min(8),
});

export type SignUpState = { error?: string };

export async function signUpAction(_prev: SignUpState, formData: FormData): Promise<SignUpState> {
  const t = await getTranslations('auth');

  const parsed = schema.safeParse({
    email:    formData.get('email'),
    password: formData.get('password'),
  });
  if (!parsed.success) return { error: t('invalidEmailOrPassword') };

  const client = apiClient();

  try {
    await client.signUp(parsed.data);
    const { token, refresh_token } = await client.login(parsed.data);
    await persistTokens(token, refresh_token);
  } catch (error) {
    if (error instanceof Error && error.message.includes('already exists')) {
      return { error: t('accountExists') };
    }
    // The user only sees the generic message — the real cause must reach the server log.
    console.error('signUpAction failed:', error);
    return { error: t('createAccountFailed') };
  }

  redirect('/dashboard');
}
