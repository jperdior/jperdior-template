import { expect, test, type Browser, type BrowserContext, type Page } from '@playwright/test';
import { anyName, DASHBOARD_URL, LOCALES, p, t } from './helpers/i18n';
import { logIn, signOut, signUp } from './helpers/auth';

const baseURL = process.env.PLAYWRIGHT_BASE_URL ?? 'http://localhost:3000';

// One ordered journey per locale, sharing a single browser context (one session)
// across steps — the real first-visit flow: browse anonymously → create ONE account
// → log out → log back in with it. Because the whole run has a fresh, isolated DB, a
// single account per locale is all it needs.
for (const locale of LOCALES) {
  const { code, prefix, messages } = locale;

  test.describe.serial(`journey (${code})`, () => {
    let context: BrowserContext;
    let page: Page;
    let account: { email: string; password: string };

    test.beforeAll(async ({ browser }: { browser: Browser }) => {
      context = await browser.newContext({ baseURL });
      page = await context.newPage();
    });

    test.afterAll(async () => {
      await context.close();
    });

    test('anon: a protected page redirects to login with a next param', async () => {
      await page.goto(p(prefix, '/dashboard'));
      // The auth guard redirects to the unprefixed `/login`, preserving the original
      // (locale-prefixed) destination in `next`.
      await page.waitForURL(
        (url) => url.pathname === '/login' && url.searchParams.get('next') === p(prefix, '/dashboard'),
      );
      await expect(page.getByRole('heading', { name: anyName('auth.signInTitle') })).toBeVisible();
    });

    test('anon: the home page shows the localized landing content', async () => {
      await page.goto(p(prefix, '/'));
      await expect(page.getByText(t(messages, 'common.appDescription'))).toBeVisible();
      await expect(page.getByRole('link', { name: t(messages, 'home.signIn'), exact: true })).toBeVisible();
      await expect(page.getByRole('link', { name: t(messages, 'home.createAccount'), exact: true })).toBeVisible();
    });

    test('sign up → lands on the dashboard', async () => {
      account = await signUp(page, locale);
      await expect(page.getByRole('heading', { name: anyName('dashboard.welcome') })).toBeVisible();
      await expect(page.getByText(account.email)).toBeVisible();
    });

    test('log out → back to a login page', async () => {
      await signOut(page);
    });

    test('log back in with that account → dashboard', async () => {
      await logIn(page, locale, account);
      await expect(page).not.toHaveURL(/\/login/);
      await expect(page).toHaveURL(DASHBOARD_URL);
      await expect(page.getByRole('heading', { name: anyName('dashboard.welcome') })).toBeVisible();
    });
  });
}
