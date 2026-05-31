'use server';

import { redirect } from 'next/navigation';
import { apiClient } from '@jperdior/api-client-ts/server';

export type ResetPasswordState = { error?: string };

export async function resetPasswordAction(
  _prev: ResetPasswordState,
  formData: FormData,
): Promise<ResetPasswordState> {
  const newPassword = (formData.get('newPassword') as string | null) ?? '';
  const confirm     = (formData.get('confirm')     as string | null) ?? '';

  if (newPassword.length < 8) return { error: 'Password must be at least 8 characters.' };
  if (newPassword !== confirm) return { error: 'Passwords do not match.' };

  try {
    await apiClient().selfResetPassword(newPassword);
  } catch (e) {
    return { error: (e as { message?: string } | null)?.message ?? 'Failed to reset password.' };
  }

  redirect('/dashboard');
}
