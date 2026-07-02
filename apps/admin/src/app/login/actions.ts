'use server';

import { createSignInAction, type SignInState } from '@jperdior/auth-server';

export type LoginState = SignInState;

// Rejects non-admin logins BEFORE any cookie is persisted (and clears stale ones).
const signIn = createSignInAction({
  authorize: (me) => me.roles.includes('ROLE_ADMIN') || 'This account does not have admin access.',
});

export async function loginAction(prev: LoginState, formData: FormData): Promise<LoginState> {
  return signIn(prev, formData);
}
