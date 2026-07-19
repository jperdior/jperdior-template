import { readFileSync } from 'node:fs';
import { fileURLToPath } from 'node:url';

// Read the catalogs at runtime rather than `import … from '*.json'` — under
// Playwright's ESM loader a JSON default import requires an import attribute,
// which the eslint/transform toolchain does not uniformly support.
function loadCatalog(file: string): unknown {
  const path = fileURLToPath(new URL(`../../messages/${file}`, import.meta.url));
  return JSON.parse(readFileSync(path, 'utf8'));
}

const en = loadCatalog('en.json');
const es = loadCatalog('es.json');

/**
 * Locale fixture for parametrizing specs. `en` is the default locale and lives at
 * unprefixed URLs (`localePrefix: 'as-needed'`); `es` lives under `/es`. Each entry
 * carries its own message catalog so assertions read exactly what a user in that
 * language sees — no duplicated string literals in the tests.
 */
export const LOCALES = [
  { code: 'en', prefix: '', messages: en },
  { code: 'es', prefix: '/es', messages: es },
] as const;

export type LocaleFixture = (typeof LOCALES)[number];

/** Build a locale-aware path: p('', '/signup') → '/signup'; p('/es', '/signup') → '/es/signup'. */
export function p(prefix: string, path: string): string {
  if (path === '/') return prefix || '/';
  return `${prefix}${path}`;
}

/** Look up a dotted message key in a locale catalog: t(loc.messages, 'nav.signOut'). */
export function t(messages: unknown, key: string): string {
  const value = key
    .split('.')
    .reduce<unknown>((acc, k) => (acc as Record<string, unknown> | undefined)?.[k], messages);
  if (typeof value !== 'string') {
    throw new Error(`Missing message key "${key}" in catalog`);
  }
  return value;
}

function escapeRegExp(source: string): string {
  return source.replace(/[.*+?^${}()|[\]\\]/g, '\\$&');
}

/**
 * A regex matching a key's value across ALL locales. Use for controls that appear
 * AFTER a server-action redirect, whose rendered locale is not guaranteed (e.g.
 * `redirect('/dashboard')` from an `/es/…` page, or the auth guard's `/login`).
 * Anchored to full-string.
 */
export function anyName(key: string): RegExp {
  const names = LOCALES.map((locale) => escapeRegExp(t(locale.messages, key)));
  return new RegExp(`^(?:${names.join('|')})$`);
}

/** Matches `/dashboard` or `/es/dashboard` (locale-tolerant destination). */
export const DASHBOARD_URL = /\/(?:es\/)?dashboard(?:[/?]|$)/;

/** Matches `/login` or `/es/login` (locale-tolerant destination). */
export const LOGIN_URL = /\/(?:es\/)?login(?:[/?]|$)/;
