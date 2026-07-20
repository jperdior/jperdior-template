import { chromium } from '@playwright/test';
import { LOCALES } from './helpers/i18n';
import { signUp } from './helpers/auth';

/**
 * The `web` service runs `next dev`, which compiles routes on demand on first hit.
 * Under `fullyParallel`, several workers would each cold-hit the same route at once
 * and block on a single shared compile long enough to blow the per-test timeout.
 *
 * Warm every route the suite touches ONCE, up front (serially, generous timeout),
 * so the parallel workers only ever hit already-compiled routes.
 */
export default async function globalSetup(): Promise<void> {
  const baseURL = process.env.PLAYWRIGHT_BASE_URL ?? 'http://localhost:3000';
  const browser = await chromium.launch();
  const page = await browser.newPage({ baseURL });
  page.setDefaultTimeout(120_000);
  page.setDefaultNavigationTimeout(120_000);

  try {
    // English first — visiting an `/es` route sets the NEXT_LOCALE cookie, after
    // which next-intl's locale detection redirects unprefixed paths to `/es`.
    await page.goto('/', { waitUntil: 'load' }).catch(() => undefined);
    await page.goto('/login', { waitUntil: 'load' }).catch(() => undefined);
    // A real signup compiles `/signup` and the shared authenticated `/dashboard`
    // route segment (compiled once, used by both locales).
    await signUp(page, LOCALES[0]);
    // Spanish routes are explicitly prefixed, so they render `es` regardless of the
    // cookie set above.
    for (const route of ['/es', '/es/login', '/es/signup']) {
      await page.goto(route, { waitUntil: 'load' }).catch(() => undefined);
    }
  } finally {
    await browser.close();
  }
}
