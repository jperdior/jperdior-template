import { type Page } from '@playwright/test';
import { anyName, DASHBOARD_URL, LOGIN_URL, p, t, type LocaleFixture } from './i18n';

interface Credentials {
  email: string;
  password: string;
}

/**
 * Sign up a fresh, unique user through the signup form for the given locale, and
 * wait until the post-signup redirect lands on the dashboard. The signup page's
 * locale is fixed by its URL prefix, so field labels + submit are matched with the
 * locale's exact catalog strings; the dashboard destination is matched loosely (the
 * `redirect('/dashboard')` server action does not carry a locale prefix).
 */
export async function signUp(
  page: Page,
  locale: LocaleFixture,
  options?: { emailPrefix?: string; password?: string },
): Promise<Credentials> {
  const suffix = `${Date.now()}-${Math.random().toString(36).slice(2, 8)}`;
  const email = `${options?.emailPrefix ?? 'e2e'}-${suffix}@example.com`;
  const password = options?.password ?? 'e2e-secret-pass';
  const { messages, prefix } = locale;

  await page.goto(p(prefix, '/signup'));
  await page.getByLabel(t(messages, 'auth.emailLabel')).fill(email);
  await page.getByLabel(t(messages, 'auth.passwordLabel')).fill(password);
  await page.getByRole('button', { name: t(messages, 'auth.createAccountButton') }).click();
  await page.waitForURL(DASHBOARD_URL, { waitUntil: 'commit' });

  return { email, password };
}

/**
 * Sign out via the header form. The control's rendered locale is not guaranteed (we
 * may have landed on the default-locale dashboard after signup), so the button is
 * matched across all locales. Resolves once the redirect to a login page commits.
 */
export async function signOut(page: Page): Promise<void> {
  await page.getByRole('button', { name: anyName('nav.signOut') }).click();
  await page.waitForURL(LOGIN_URL);
}

/**
 * Log in an existing user through the login form for the given locale. The login
 * form defaults `next` to `/dashboard`, so it lands on the dashboard (matched
 * loosely, since the redirect target is unprefixed).
 */
export async function logIn(
  page: Page,
  locale: LocaleFixture,
  credentials: Credentials,
): Promise<void> {
  const { messages, prefix } = locale;

  await page.goto(p(prefix, '/login'));
  await page.getByLabel(t(messages, 'auth.emailLabel')).fill(credentials.email);
  await page.getByLabel(t(messages, 'auth.passwordLabel')).fill(credentials.password);
  await page.getByRole('button', { name: t(messages, 'auth.signInButton') }).click();
  await page.waitForURL(DASHBOARD_URL, { waitUntil: 'commit' });
}
