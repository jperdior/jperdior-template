import { useTranslations } from 'next-intl';
import { Button } from '@jperdior/ui-react';
import { Link } from '@/i18n/navigation';

export default function NotFound() {
  const t = useTranslations('common');
  return (
    <main className="mx-auto flex min-h-screen max-w-md flex-col items-center justify-center gap-4 px-6 py-16 text-center">
      <h1 className="text-3xl font-semibold">{t('notFoundHeading')}</h1>
      <p className="text-muted-foreground">{t('notFoundMessage')}</p>
      <Button asChild>
        <Link href="/">{t('backHome')}</Link>
      </Button>
    </main>
  );
}
