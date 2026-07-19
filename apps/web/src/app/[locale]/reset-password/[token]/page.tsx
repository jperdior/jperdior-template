import type { Metadata } from 'next';
import { getTranslations } from 'next-intl/server';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@jperdior/ui-react';
import { ResetPasswordWithTokenForm } from './ResetPasswordWithTokenForm';

export async function generateMetadata({
  params,
}: {
  params: Promise<{ locale: string }>;
}): Promise<Metadata> {
  const { locale } = await params;
  const t = await getTranslations({ locale, namespace: 'auth' });
  return {
    title: t('setNewPasswordMetaTitle'),
    // The token is in the URL — block Referer leakage to any external resource.
    referrer: 'no-referrer',
  };
}

export default async function ResetPasswordWithTokenPage({
  params,
}: {
  params: Promise<{ token: string; locale: string }>;
}) {
  const { token } = await params;
  const t = await getTranslations('auth');
  return (
    <main className="mx-auto flex min-h-screen max-w-md flex-col justify-center px-6">
      <Card>
        <CardHeader>
          <CardTitle>{t('setNewPasswordTitle')}</CardTitle>
          <CardDescription>
            {t('setNewPasswordDescription')}
          </CardDescription>
        </CardHeader>
        <CardContent>
          <ResetPasswordWithTokenForm token={token} />
        </CardContent>
      </Card>
    </main>
  );
}
