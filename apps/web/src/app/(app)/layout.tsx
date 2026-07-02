import type { ReactNode } from 'react';
import Link from 'next/link';
import { redirect } from 'next/navigation';
import { Button } from '@jperdior/ui-react';
import { isAuthenticated, clearTokens } from '@jperdior/auth-server';

async function signOut() {
  'use server';
  await clearTokens();
  redirect('/login');
}

export default async function AppLayout({ children }: { children: ReactNode }) {
  if (!(await isAuthenticated())) redirect('/login');

  return (
    <div className="min-h-screen">
      <header className="border-b bg-card">
        <div className="mx-auto flex max-w-5xl items-center justify-between px-6 py-3">
          <Link href="/dashboard" className="text-sm font-semibold">
            jperdior
          </Link>
          <form action={signOut}>
            <Button type="submit" variant="ghost" size="sm">
              Sign out
            </Button>
          </form>
        </div>
      </header>
      {children}
    </div>
  );
}
