import { expect, test } from '@playwright/test';
import { signUp } from './helpers/auth';

test('signup → create note → see it on the list', async ({ page }) => {
  await signUp(page);

  // empty state
  await expect(page.getByText('No notes yet')).toBeVisible();

  // new note flow
  await page.getByRole('link', { name: 'New note' }).first().click();
  await page.getByLabel('Title').fill('e2e note');
  await page.getByLabel('Body').fill('hello from playwright');
  await page.getByRole('button', { name: 'Save' }).click();

  // detail page
  await expect(page.getByRole('heading', { name: 'e2e note' })).toBeVisible();

  // list page
  await page.getByRole('link', { name: 'jperdior · Notes' }).click();
  await expect(page.getByRole('link', { name: 'e2e note' })).toBeVisible();
});

test('protected page redirects to login when signed out', async ({ page }) => {
  await page.goto('/notes');
  await page.waitForURL('**/login?next=%2Fnotes');
  await expect(page.getByRole('heading', { name: 'Sign in' })).toBeVisible();
});
