import type { Page } from '@playwright/test';

export async function signUp(page: Page, options?: { emailPrefix?: string; password?: string }) {
  const email    = `${options?.emailPrefix ?? 'e2e'}-${Date.now()}-${Math.random().toString(36).slice(2, 8)}@example.com`;
  const password = options?.password ?? 'secretpass';

  await page.goto('/signup');
  await page.getByLabel('Email').fill(email);
  await page.getByLabel('Password').fill(password);
  await page.getByRole('button', { name: 'Create account' }).click();
  await page.waitForURL('**/notes');

  return { email, password };
}
