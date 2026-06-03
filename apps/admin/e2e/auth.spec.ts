import { expect, test } from '@playwright/test';
import { loginAsAdmin } from './helpers/auth';

test('protected page redirects to login when signed out', async ({ page }) => {
  await page.goto('/dashboard');
  await page.waitForURL('**/login?next=%2Fdashboard');
  await expect(page.getByRole('heading', { name: 'Admin sign in' })).toBeVisible();
});

test('admin login → lands on dashboard', async ({ page }) => {
  test.skip(
    !process.env.PLAYWRIGHT_ADMIN_EMAIL || !process.env.PLAYWRIGHT_ADMIN_PASSWORD,
    'Set PLAYWRIGHT_ADMIN_EMAIL and PLAYWRIGHT_ADMIN_PASSWORD to run this test',
  );

  const { email } = await loginAsAdmin(page);
  await expect(page.getByText(email)).toBeVisible();
});
