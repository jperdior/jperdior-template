'use client';

import { useActionState } from 'react';
import { useTranslations } from 'next-intl';
import { Link } from '@/i18n/navigation';
import { Button, Input, Label, Spinner } from '@jperdior/ui-react';
import { forgotPasswordAction, type ForgotPasswordState } from './actions';

export function ForgotPasswordForm() {
  const t = useTranslations('auth');
  const [state, formAction, isPending] = useActionState<ForgotPasswordState, FormData>(
    forgotPasswordAction,
    {},
  );

  if (state.sent) {
    return (
      <div className="flex flex-col gap-4">
        <p className="text-sm">{t('resetLinkSent')}</p>
        <Link href="/login" className="text-sm underline underline-offset-4">
          {t('backToSignIn')}
        </Link>
      </div>
    );
  }

  return (
    <form action={formAction} className="flex flex-col gap-4">
      <div className="space-y-2">
        <Label htmlFor="email">{t('emailLabel')}</Label>
        <Input id="email" name="email" type="email" autoComplete="email" autoFocus required />
      </div>
      {state.error && <p className="text-sm text-destructive">{state.error}</p>}
      <Button type="submit" disabled={isPending}>
        {isPending && <Spinner />}
        {t('sendResetLink')}
      </Button>
      <Link href="/login" className="text-center text-sm underline underline-offset-4">
        {t('backToSignIn')}
      </Link>
    </form>
  );
}
