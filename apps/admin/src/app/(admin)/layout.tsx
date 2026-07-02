import type { ReactNode } from 'react';
import Link from 'next/link';
import { redirect } from 'next/navigation';
import { Button } from '@jperdior/ui-react';
import { apiClient } from '@jperdior/api-client-ts/server';
import { clearTokens, isAuthenticated } from '@jperdior/auth-server'; // clearTokens used by signOut action

async function signOut() {
  'use server';
  await clearTokens();
  redirect('/login');
}

export default async function AdminLayout({ children }: { children: ReactNode }) {
  if (!(await isAuthenticated())) redirect('/login');

  // Server-side ROLE_ADMIN gate. The API also enforces it, but the layout
  // bounces non-admins early so they don't see broken pages.
  try {
    const me = await apiClient().me();
    if (!me.roles.includes('ROLE_ADMIN')) {
      redirect('/login?error=admin-required');
    }
  } catch {
    redirect('/login');
  }

  return (
    <div className="min-h-screen">
      <header className="border-b bg-card">
        <div className="mx-auto flex max-w-6xl items-center justify-between px-6 py-3">
          <div className="flex items-center gap-6">
            <Link href="/dashboard" className="text-sm font-semibold">
              jperdior · Admin
            </Link>
            <nav className="flex items-center gap-4 text-sm text-muted-foreground">
              <Link href="/dashboard" className="hover:text-foreground">Dashboard</Link>
              <Link href="/users" className="hover:text-foreground">Users</Link>
            </nav>
          </div>
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
