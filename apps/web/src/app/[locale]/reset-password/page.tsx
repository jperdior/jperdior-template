import type { Metadata } from 'next';
import { redirect, unstable_rethrow } from 'next/navigation';
import { getTranslations } from 'next-intl/server';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@jperdior/ui-react';
import { apiClient } from '@jperdior/api-client-ts/server';
import { ResetPasswordForm } from './ResetPasswordForm';

export async function generateMetadata({
  params,
}: {
  params: Promise<{ locale: string }>;
}): Promise<Metadata> {
  const { locale } = await params;
  const t = await getTranslations({ locale, namespace: 'auth' });
  return { title: t('resetPasswordMetaTitle') };
}

export default async function ResetPasswordPage() {
  const t = await getTranslations('auth');

  let mustReset = false;
  try {
    const me = await apiClient().me();
    mustReset = me.mustResetPassword;
  } catch (e) {
    // A dead session already redirects to /login?reason=expired; preserve that
    // instead of collapsing it to a bare /login.
    unstable_rethrow(e);
    redirect('/login');
  }

  if (!mustReset) redirect('/dashboard');

  return (
    <main className="mx-auto flex min-h-screen max-w-md flex-col justify-center px-6 py-16">
      <Card>
        <CardHeader>
          <CardTitle>{t('resetPasswordTitle')}</CardTitle>
          <CardDescription>{t('resetPasswordDescription')}</CardDescription>
        </CardHeader>
        <CardContent>
          <ResetPasswordForm />
        </CardContent>
      </Card>
    </main>
  );
}
