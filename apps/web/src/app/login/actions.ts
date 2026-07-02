'use server';

import { createSignInAction, type SignInState } from '@jperdior/auth-server';

export type LoginState = SignInState;

const signIn = createSignInAction({
  postSignInRedirect: (me, next) => (me?.mustResetPassword ? '/reset-password' : next),
});

export async function loginAction(prev: LoginState, formData: FormData): Promise<LoginState> {
  return signIn(prev, formData);
}
