import { redirect } from 'next/navigation';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@jperdior/ui-react';
import { apiClient } from '@jperdior/api-client-ts/server';
import { ResetPasswordForm } from './ResetPasswordForm';

export default async function ResetPasswordPage() {
  let mustReset = false;
  try {
    const me = await apiClient().me();
    mustReset = me.mustResetPassword;
  } catch {
    redirect('/login');
  }

  if (!mustReset) redirect('/dashboard');

  return (
    <main className="mx-auto flex min-h-screen max-w-md flex-col justify-center px-6 py-16">
      <Card>
        <CardHeader>
          <CardTitle>Reset your password</CardTitle>
          <CardDescription>You must set a new password before continuing.</CardDescription>
        </CardHeader>
        <CardContent>
          <ResetPasswordForm />
        </CardContent>
      </Card>
    </main>
  );
}
