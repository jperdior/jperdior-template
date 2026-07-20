import { defineConfig, devices } from '@playwright/test';

export default defineConfig({
  testDir: './e2e',
  globalSetup: './e2e/global-setup.ts',
  fullyParallel: true,
  forbidOnly: !!process.env.CI,
  // The `web` service is `next dev`; even a warmed route is slower than a prod
  // build, so allow generous headroom over the 30s default.
  timeout: 60_000,
  // Two serial journeys (en + es), one signup each — one worker per locale.
  workers: 2,
  expect: { timeout: 15_000 },
  // Retries absorb rare dev-stack stalls (a signup/login action whose API call hits
  // an on-demand recompile) that no reasonable timeout can rule out against `next dev`.
  retries: 2,
  reporter: process.env.CI
    ? [['list'], ['html', { outputFolder: 'playwright-report', open: 'never' }]]
    : 'list',
  use: {
    baseURL: process.env.PLAYWRIGHT_BASE_URL ?? 'http://localhost:3000',
    trace: 'on-first-retry',
  },
  projects: [
    {
      name: 'chromium',
      use: { ...devices['Desktop Chrome'] },
    },
  ],
});
