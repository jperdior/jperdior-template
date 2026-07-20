'use server';

import { redirect, unstable_rethrow } from 'next/navigation';
import { getTranslations } from 'next-intl/server';
import { apiClient } from '@jperdior/api-client-ts/server';

export type ResetPasswordState = { error?: string };

export async function resetPasswordAction(
  _prev: ResetPasswordState,
  formData: FormData,
): Promise<ResetPasswordState> {
  const t = await getTranslations('auth');
  const newPassword = (formData.get('newPassword') as string | null) ?? '';
  const confirm     = (formData.get('confirm')     as string | null) ?? '';

  if (newPassword.length < 8) return { error: t('passwordTooShort') };
  if (newPassword !== confirm) return { error: t('passwordsMismatch') };

  try {
    await apiClient().selfResetPassword(newPassword);
  } catch (e) {
    // A dead session redirects to login (control-flow signal) — let it through.
    unstable_rethrow(e);
    return { error: (e as { message?: string } | null)?.message ?? t('resetPasswordFailed') };
  }

  redirect('/dashboard');
}
