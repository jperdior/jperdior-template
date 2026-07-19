'use client';

import { useActionState, useEffect } from 'react';
import { useTranslations } from 'next-intl';
import { Link, useRouter } from '@/i18n/navigation';
import { Button, Input, Label, Spinner } from '@jperdior/ui-react';
import { resetPasswordWithTokenAction, type ResetPasswordWithTokenState } from './actions';

export function ResetPasswordWithTokenForm({ token }: { token: string }) {
  const t = useTranslations('auth');
  const router = useRouter();
  const [state, formAction, isPending] = useActionState<ResetPasswordWithTokenState, FormData>(
    resetPasswordWithTokenAction,
    {},
  );

  useEffect(() => {
    if (state.done) router.push('/login?reset=1');
  }, [state.done, router]);

  return (
    <form action={formAction} className="flex flex-col gap-4">
      <input type="hidden" name="token" value={token} />
      <div className="space-y-2">
        <Label htmlFor="newPassword">{t('newPasswordLabel')}</Label>
        <Input
          id="newPassword"
          name="newPassword"
          type="password"
          autoComplete="new-password"
          minLength={8}
          maxLength={4096}
          autoFocus
          required
        />
      </div>
      <div className="space-y-2">
        <Label htmlFor="confirm">{t('confirmPasswordLabel')}</Label>
        <Input
          id="confirm"
          name="confirm"
          type="password"
          autoComplete="new-password"
          minLength={8}
          maxLength={4096}
          required
        />
      </div>
      {state.error && <p className="text-sm text-destructive">{state.error}</p>}
      <Button type="submit" disabled={isPending || state.done}>
        {isPending && <Spinner />}
        {t('setNewPassword')}
      </Button>
      <Link href="/forgot-password" className="text-center text-sm underline underline-offset-4">
        {t('requestNewLink')}
      </Link>
    </form>
  );
}
