'use server';

import { z } from 'zod';
import { ApiError } from '@jperdior/api-client-ts';
import { apiClient } from '@jperdior/api-client-ts/server';

const schema = z
  .object({
    token:       z.string().regex(/^[a-f0-9]{96}$/),
    newPassword: z.string().min(8).max(4096),
    confirm:     z.string(),
  })
  .refine((d) => d.newPassword === d.confirm, {
    path: ['confirm'],
    message: 'Passwords do not match.',
  });

export type ResetPasswordWithTokenState = { error?: string; done?: boolean };

export async function resetPasswordWithTokenAction(
  _prev: ResetPasswordWithTokenState,
  formData: FormData,
): Promise<ResetPasswordWithTokenState> {
  const parsed = schema.safeParse({
    token:       formData.get('token'),
    newPassword: formData.get('newPassword'),
    confirm:     formData.get('confirm'),
  });
  if (!parsed.success) {
    const issues = parsed.error.issues;
    const mismatch = issues.find((i) => i.path[0] === 'confirm');
    if (mismatch) return { error: mismatch.message };
    return { error: 'Invalid request. Please request a new password reset.' };
  }

  const client = apiClient();

  try {
    await client.resetPasswordWithToken(parsed.data.token, parsed.data.newPassword);
  } catch (e) {
    if (e instanceof ApiError) {
      if (e.status === 404) return { error: 'This password reset link is invalid.' };
      if (e.status === 422) return { error: 'This password reset link has expired or has already been used.' };
      if (e.status === 429) return { error: 'Too many attempts. Please wait a moment and try again.' };
    }
    return { error: 'Failed to reset your password. Please try again.' };
  }

  return { done: true };
}
