'use client';

import { useActionState } from 'react';
import { useTranslations } from 'next-intl';
import { Button, Input, Label, Spinner } from '@jperdior/ui-react';
import { resetPasswordAction, type ResetPasswordState } from './actions';

export function ResetPasswordForm() {
  const t = useTranslations('auth');
  const [state, formAction, isPending] = useActionState<ResetPasswordState, FormData>(
    resetPasswordAction,
    {},
  );

  return (
    <form action={formAction} className="flex flex-col gap-4">
      <div className="space-y-2">
        <Label htmlFor="newPassword">{t('newPasswordLabel')}</Label>
        <Input
          id="newPassword"
          name="newPassword"
          type="password"
          minLength={8}
          required
          autoFocus
        />
      </div>
      <div className="space-y-2">
        <Label htmlFor="confirm">{t('confirmPasswordLabel')}</Label>
        <Input
          id="confirm"
          name="confirm"
          type="password"
          minLength={8}
          required
        />
      </div>
      {state.error && <p className="text-sm text-destructive">{state.error}</p>}
      <Button type="submit" disabled={isPending}>
        {isPending && <Spinner />}
        {t('setNewPassword')}
      </Button>
    </form>
  );
}
