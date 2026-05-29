'use client';

import { ErrorState } from '@jperdior/ui-react';

export default function Error({ error, reset }: { error: Error; reset: () => void }) {
  return (
    <main className="mx-auto max-w-2xl px-6 py-16">
      <ErrorState message={error.message || 'Something went wrong.'} onRetry={reset} />
    </main>
  );
}
