'use client';

import { useActionState } from 'react';
import { useSearchParams } from 'next/navigation';
import { Button, Input, Label, Spinner } from '@jperdior/ui-react';
import { loginAction, type LoginState } from './actions';

export function LoginForm() {
  const search = useSearchParams();
  const next   = search.get('next') ?? '/users';
  const [state, formAction, isPending] = useActionState<LoginState, FormData>(loginAction, {});

  return (
    <form action={formAction} className="flex flex-col gap-4">
      <input type="hidden" name="next" value={next} />
      <div className="space-y-2">
        <Label htmlFor="email">Email</Label>
        <Input id="email" name="email" type="email" autoComplete="email" autoFocus required />
      </div>
      <div className="space-y-2">
        <Label htmlFor="password">Password</Label>
        <Input id="password" name="password" type="password" autoComplete="current-password" required />
      </div>
      {state.error && <p className="text-sm text-destructive">{state.error}</p>}
      <Button type="submit" disabled={isPending}>
        {isPending && <Spinner />}
        Sign in
      </Button>
    </form>
  );
}
