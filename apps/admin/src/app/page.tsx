import Link from 'next/link';
import { redirect } from 'next/navigation';
import { Button, Card, CardContent, CardDescription, CardHeader, CardTitle } from '@jperdior/ui-react';
import { isAuthenticated } from '@jperdior/auth-server';

export default async function AdminLandingPage() {
  if (await isAuthenticated()) redirect('/dashboard');

  return (
    <main className="mx-auto flex min-h-screen max-w-2xl flex-col justify-center px-6 py-16">
      <Card>
        <CardHeader>
          <CardTitle>jperdior — Admin</CardTitle>
          <CardDescription>Back-office. ROLE_ADMIN required.</CardDescription>
        </CardHeader>
        <CardContent>
          <Button asChild>
            <Link href="/login">Sign in</Link>
          </Button>
        </CardContent>
      </Card>
    </main>
  );
}
