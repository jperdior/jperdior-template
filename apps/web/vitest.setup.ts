import '@testing-library/jest-dom/vitest';
import { vi } from 'vitest';
import React from 'react';

type Messages = Record<string, unknown>;

// Resolve a dotted key path (optionally under a namespace) against the real en catalog,
// so component tests assert on actual English copy without a live NextIntlClientProvider.
function makeTranslator(messages: Messages) {
  return (namespace?: string) =>
    (key: string): string => {
      const path = namespace ? `${namespace}.${key}` : key;
      const value = path
        .split('.')
        .reduce<unknown>((acc, part) => (acc as Messages | undefined)?.[part], messages);
      return typeof value === 'string' ? value : path;
    };
}

vi.mock('next-intl', async () => {
  const messages = (await import('./messages/en.json')).default as Messages;
  const translator = makeTranslator(messages);
  return {
    useTranslations: (namespace?: string) => translator(namespace),
    NextIntlClientProvider: ({ children }: { children: React.ReactNode }) => children,
    hasLocale: (locales: readonly string[], locale: unknown) =>
      typeof locale === 'string' && locales.includes(locale),
  };
});

vi.mock('next-intl/server', async () => {
  const messages = (await import('./messages/en.json')).default as Messages;
  const translator = makeTranslator(messages);
  return {
    getTranslations: async (arg?: string | { namespace?: string }) =>
      translator(typeof arg === 'string' ? arg : arg?.namespace),
    setRequestLocale: () => {},
  };
});

vi.mock('@/i18n/navigation', () => ({
  Link: ({ href, children, ...rest }: React.ComponentProps<'a'> & { href: string }) =>
    React.createElement('a', { href, ...rest }, children),
  useRouter: () => ({ push: vi.fn(), replace: vi.fn(), refresh: vi.fn(), back: vi.fn() }),
  usePathname: () => '/',
  redirect: vi.fn(),
  getPathname: ({ href }: { href: string }) => href,
}));

vi.mock('next/link', () => ({
  default: ({ href, children, ...rest }: React.ComponentProps<'a'> & { href: string }) =>
    React.createElement('a', { href, ...rest }, children),
}));

vi.mock('next/navigation', () => ({
  useRouter: () => ({ push: vi.fn(), replace: vi.fn(), refresh: vi.fn(), back: vi.fn() }),
  usePathname: () => '/',
  useSearchParams: () => new URLSearchParams(),
  redirect: vi.fn(),
  notFound: vi.fn(),
  unstable_rethrow: vi.fn(),
}));
