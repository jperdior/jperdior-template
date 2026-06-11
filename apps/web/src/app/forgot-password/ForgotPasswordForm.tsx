'use client';

import { useActionState } from 'react';
import Link from 'next/link';
import { Button, Input, Label, Spinner } from '@jperdior/ui-react';
import { forgotPasswordAction, type ForgotPasswordState } from './actions';

export function ForgotPasswordForm() {
  const [state, formAction, isPending] = useActionState<ForgotPasswordState, FormData>(
    forgotPasswordAction,
    {},
  );

  if (state.sent) {
    return (
      <div className="flex flex-col gap-4">
        <p className="text-sm">
          If an account with that email exists, a password reset link has been sent. The link
          is valid for 1 hour.
        </p>
        <Link href="/login" className="text-sm underline underline-offset-4">
          Back to sign in
        </Link>
      </div>
    );
  }

  return (
    <form action={formAction} className="flex flex-col gap-4">
      <div className="space-y-2">
        <Label htmlFor="email">Email</Label>
        <Input id="email" name="email" type="email" autoComplete="email" autoFocus required />
      </div>
      {state.error && <p className="text-sm text-destructive">{state.error}</p>}
      <Button type="submit" disabled={isPending}>
        {isPending && <Spinner />}
        Send reset link
      </Button>
      <Link href="/login" className="text-center text-sm underline underline-offset-4">
        Back to sign in
      </Link>
    </form>
  );
}
