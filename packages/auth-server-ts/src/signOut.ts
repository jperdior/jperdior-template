import { redirect } from 'next/navigation';
import { clearTokens } from './cookies';

export function createSignOutAction(config: { redirectTo?: string } = {}) {
  const redirectTo = config.redirectTo ?? '/login';

  return async function signOut(): Promise<void> {
    await clearTokens();
    redirect(redirectTo);
  };
}
