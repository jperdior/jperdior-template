'use server';

import { z } from 'zod';
import { apiClient } from '@jperdior/api-client-ts/server';

const schema = z.object({ email: z.string().email() });

export type ForgotPasswordState = { error?: string; sent?: boolean };

export async function forgotPasswordAction(
  _prev: ForgotPasswordState,
  formData: FormData,
): Promise<ForgotPasswordState> {
  const parsed = schema.safeParse({ email: formData.get('email') });
  if (!parsed.success) return { error: 'Please enter a valid email address.' };

  const client = apiClient();

  try {
    await client.forgotPassword(parsed.data.email);
  } catch (error) {
    // Always render success regardless of outcome to prevent user enumeration (BR-U05) —
    // but the failure itself must be visible in the server log.
    console.error('forgotPasswordAction failed (204 still rendered per BR-U05):', error);
  }

  return { sent: true };
}
