'use client';

import { useActionState } from 'react';
import { Button, Input, Label, Spinner } from '@jperdior/ui-react';
import { signUpAction, type SignUpState } from './actions';

export function SignUpForm() {
  const [state, formAction, isPending] = useActionState<SignUpState, FormData>(signUpAction, {});

  return (
    <form action={formAction} className="flex flex-col gap-4">
      <div className="space-y-2">
        <Label htmlFor="email">Email</Label>
        <Input id="email" name="email" type="email" autoComplete="email" autoFocus required />
      </div>
      <div className="space-y-2">
        <Label htmlFor="password">Password</Label>
        <Input id="password" name="password" type="password" autoComplete="new-password" required minLength={8} />
      </div>
      {state.error && <p className="text-sm text-destructive">{state.error}</p>}
      <Button type="submit" disabled={isPending}>
        {isPending && <Spinner />}
        Create account
      </Button>
    </form>
  );
}
