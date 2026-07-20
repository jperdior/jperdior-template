import type { Metadata } from 'next';
import { getTranslations } from 'next-intl/server';
import { useTranslations } from 'next-intl';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@jperdior/ui-react';
import { ForgotPasswordForm } from './ForgotPasswordForm';

export async function generateMetadata({
  params,
}: {
  params: Promise<{ locale: string }>;
}): Promise<Metadata> {
  const { locale } = await params;
  const t = await getTranslations({ locale, namespace: 'auth' });
  return {
    title: t('forgotPasswordMetaTitle'),
    // Prevent the link from leaking via Referer if any external resource is fetched from this page.
    referrer: 'no-referrer',
  };
}

export default function ForgotPasswordPage() {
  const t = useTranslations('auth');
  return (
    <main className="mx-auto flex min-h-screen max-w-md flex-col justify-center px-6">
      <Card>
        <CardHeader>
          <CardTitle>{t('forgotPasswordTitle')}</CardTitle>
          <CardDescription>{t('forgotPasswordDescription')}</CardDescription>
        </CardHeader>
        <CardContent>
          <ForgotPasswordForm />
        </CardContent>
      </Card>
    </main>
  );
}
