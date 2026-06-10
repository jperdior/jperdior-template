'use server';

import { z } from 'zod';
import { createApiClient } from '@jperdior/api-client-ts';

const schema = z.object({ email: z.string().email() });

export type ForgotPasswordState = { error?: string; sent?: boolean };

export async function forgotPasswordAction(
  _prev: ForgotPasswordState,
  formData: FormData,
): Promise<ForgotPasswordState> {
  const parsed = schema.safeParse({ email: formData.get('email') });
  if (!parsed.success) return { error: 'Please enter a valid email address.' };

  const client = createApiClient({ baseUrl: process.env.INTERNAL_API_URL ?? 'http://nginx:80' });

  try {
    await client.forgotPassword(parsed.data.email);
  } catch {
    // Always render success regardless of outcome to prevent user enumeration (BR-U05).
  }

  return { sent: true };
}
