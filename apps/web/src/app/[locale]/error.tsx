'use client';

import { useTranslations } from 'next-intl';
import { ErrorState } from '@jperdior/ui-react';

export default function Error({ error, reset }: { error: Error; reset: () => void }) {
  const t = useTranslations('common');
  return (
    <main className="mx-auto max-w-2xl px-6 py-16">
      <ErrorState message={error.message || t('errorFallback')} onRetry={reset} />
    </main>
  );
}
