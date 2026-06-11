import type { Metadata } from 'next';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@jperdior/ui-react';
import { ResetPasswordWithTokenForm } from './ResetPasswordWithTokenForm';

export const metadata: Metadata = {
  title: 'Set new password',
  // The token is in the URL — block Referer leakage to any external resource.
  referrer: 'no-referrer',
};

export default async function ResetPasswordWithTokenPage({
  params,
}: {
  params: Promise<{ token: string }>;
}) {
  const { token } = await params;
  return (
    <main className="mx-auto flex min-h-screen max-w-md flex-col justify-center px-6">
      <Card>
        <CardHeader>
          <CardTitle>Set a new password</CardTitle>
          <CardDescription>
            Choose a strong password you have not used elsewhere.
          </CardDescription>
        </CardHeader>
        <CardContent>
          <ResetPasswordWithTokenForm token={token} />
        </CardContent>
      </Card>
    </main>
  );
}
