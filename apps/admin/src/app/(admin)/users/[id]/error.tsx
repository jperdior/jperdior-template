'use client';

import { ErrorState } from '@jperdior/ui-react';

export default function UserDetailError({ reset }: { reset: () => void }) {
  return <ErrorState message="Failed to load user details." onRetry={reset} />;
}
