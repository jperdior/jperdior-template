import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@jperdior/ui-react';
import { useTranslations } from 'next-intl';
import { LoginForm } from './LoginForm';

export default function LoginPage() {
  const t = useTranslations('auth');

  return (
    <main className="mx-auto flex min-h-screen max-w-md flex-col justify-center px-6">
      <Card>
        <CardHeader>
          <CardTitle>{t('signInTitle')}</CardTitle>
          <CardDescription>{t('signInDescription')}</CardDescription>
        </CardHeader>
        <CardContent>
          <LoginForm />
        </CardContent>
      </Card>
    </main>
  );
}
