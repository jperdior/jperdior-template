'use client';

import { useActionState, useEffect } from 'react';
import Link from 'next/link';
import { useRouter } from 'next/navigation';
import { Button, Input, Label, Spinner } from '@jperdior/ui-react';
import { resetPasswordWithTokenAction, type ResetPasswordWithTokenState } from './actions';

export function ResetPasswordWithTokenForm({ token }: { token: string }) {
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
        <Label htmlFor="newPassword">New password</Label>
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
        <Label htmlFor="confirm">Confirm password</Label>
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
        Set new password
      </Button>
      <Link href="/forgot-password" className="text-center text-sm underline underline-offset-4">
        Request a new link
      </Link>
    </form>
  );
}
