import type { ReactNode } from 'react';
import { redirect } from 'next/navigation';
import { getTranslations } from 'next-intl/server';
import { Button } from '@jperdior/ui-react';
import { Link } from '@/i18n/navigation';
import { createSignOutAction, isAuthenticated } from '@jperdior/auth-server';

const doSignOut = createSignOutAction();

async function signOut() {
  'use server';
  await doSignOut();
}

export default async function AppLayout({ children }: { children: ReactNode }) {
  if (!(await isAuthenticated())) redirect('/login');

  const t = await getTranslations('nav');

  return (
    <div className="min-h-screen">
      <header className="border-b bg-card">
        <div className="mx-auto flex max-w-5xl items-center justify-between px-6 py-3">
          <Link href="/dashboard" className="text-sm font-semibold">
            {t('appName')}
          </Link>
          <form action={signOut}>
            <Button type="submit" variant="ghost" size="sm">
              {t('signOut')}
            </Button>
          </form>
        </div>
      </header>
      {children}
    </div>
  );
}
