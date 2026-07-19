'use server';

import { z } from 'zod';
import { getTranslations } from 'next-intl/server';
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
  const t = await getTranslations('auth');
  const parsed = schema.safeParse({
    token:       formData.get('token'),
    newPassword: formData.get('newPassword'),
    confirm:     formData.get('confirm'),
  });
  if (!parsed.success) {
    const issues = parsed.error.issues;
    const mismatch = issues.find((i) => i.path[0] === 'confirm');
    if (mismatch) return { error: t('passwordsMismatch') };
    return { error: t('invalidResetRequest') };
  }

  const client = apiClient();

  try {
    await client.resetPasswordWithToken(parsed.data.token, parsed.data.newPassword);
  } catch (e) {
    if (e instanceof ApiError) {
      if (e.status === 404) return { error: t('invalidResetLink') };
      if (e.status === 422) return { error: t('expiredResetLink') };
      if (e.status === 429) return { error: t('tooManyAttempts') };
    }
    console.error('resetPasswordWithTokenAction failed:', e);
    return { error: t('resetPasswordFailed') };
  }

  return { done: true };
}
