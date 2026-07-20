import { useTranslations } from 'next-intl';
import { Button, Card, CardContent, CardDescription, CardHeader, CardTitle } from '@jperdior/ui-react';
import { Link } from '@/i18n/navigation';

export default function HomePage() {
  const t = useTranslations();
  return (
    <main className="mx-auto flex min-h-screen max-w-2xl flex-col justify-center px-6 py-16">
      <Card>
        <CardHeader>
          <CardTitle>{t('common.appName')}</CardTitle>
          <CardDescription>{t('common.appDescription')}</CardDescription>
        </CardHeader>
        <CardContent className="flex gap-3">
          <Button asChild>
            <Link href="/login">{t('home.signIn')}</Link>
          </Button>
          <Button asChild variant="outline">
            <Link href="/signup">{t('home.createAccount')}</Link>
          </Button>
        </CardContent>
      </Card>
    </main>
  );
}
