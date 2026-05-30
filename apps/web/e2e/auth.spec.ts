import { expect, test } from '@playwright/test';
import { signUp } from './helpers/auth';

test('signup → lands on dashboard showing user email', async ({ page }) => {
  const { email } = await signUp(page);
  await expect(page.getByText(email)).toBeVisible();
});

test('protected page redirects to login when signed out', async ({ page }) => {
  await page.goto('/dashboard');
  await page.waitForURL('**/login?next=%2Fdashboard');
  await expect(page.getByRole('heading', { name: 'Sign in' })).toBeVisible();
});
