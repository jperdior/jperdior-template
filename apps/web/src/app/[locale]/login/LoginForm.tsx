'use client';

import { useActionState } from 'react';
import { useSearchParams } from 'next/navigation';
import { useTranslations } from 'next-intl';
import { Button, Input, Label, Spinner } from '@jperdior/ui-react';
import { Link } from '@/i18n/navigation';
import { loginAction, type LoginState } from './actions';

export function LoginForm() {
  const t = useTranslations('auth');
  const search = useSearchParams();
  const next   = search.get('next') ?? '/dashboard';
  const sessionExpired = search.get('reason') === 'expired';
  const [state, formAction, isPending] = useActionState<LoginState, FormData>(loginAction, {});

  return (
    <form action={formAction} className="flex flex-col gap-4">
      {sessionExpired && (
        <p className="rounded border border-primary/30 bg-primary/10 px-3 py-2 text-sm text-foreground">
          {t('sessionExpired')}
        </p>
      )}
      <input type="hidden" name="next" value={next} />
      <div className="space-y-2">
        <Label htmlFor="email">{t('emailLabel')}</Label>
        <Input id="email" name="email" type="email" autoComplete="email" autoFocus required />
      </div>
      <div className="space-y-2">
        <div className="flex items-center justify-between">
          <Label htmlFor="password">{t('passwordLabel')}</Label>
          <Link
            href="/forgot-password"
            className="text-xs text-muted-foreground underline-offset-4 hover:underline"
          >
            {t('forgotPassword')}
          </Link>
        </div>
        <Input id="password" name="password" type="password" autoComplete="current-password" required />
      </div>
      {state.error && <p className="text-sm text-destructive">{state.error}</p>}
      <Button type="submit" disabled={isPending}>
        {isPending && <Spinner />}
        {t('signInButton')}
      </Button>
    </form>
  );
}
