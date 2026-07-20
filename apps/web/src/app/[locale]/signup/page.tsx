import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@jperdior/ui-react';
import { useTranslations } from 'next-intl';
import { SignUpForm } from './SignUpForm';

export default function SignUpPage() {
  const t = useTranslations('auth');

  return (
    <main className="mx-auto flex min-h-screen max-w-md flex-col justify-center px-6">
      <Card>
        <CardHeader>
          <CardTitle>{t('createAccountTitle')}</CardTitle>
          <CardDescription>{t('createAccountDescription')}</CardDescription>
        </CardHeader>
        <CardContent>
          <SignUpForm />
        </CardContent>
      </Card>
    </main>
  );
}
