import { defineRouting } from 'next-intl/routing';

// English is the default and is served at `/` (no locale prefix).
// Spanish is served under `/es`. This is the `as-needed` prefix strategy.
export const routing = defineRouting({
  locales: ['en', 'es'],
  defaultLocale: 'en',
  localePrefix: 'as-needed',
});

export type Locale = (typeof routing.locales)[number];
