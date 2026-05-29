# QA Integration Testing Instructions

## Always

- Prefer executable Playwright TypeScript tests under `apps/web/e2e/` and `apps/admin/e2e/`.
- Prefer Symfony **functional tests** (PHPUnit + `WebTestCase`) for API behaviour, under `apps/api/code/tests/Functional/<Context>/`.
- Reuse shared helpers from `apps/web/e2e/helpers/` (or `apps/admin/e2e/helpers/`).
- Keep tests independent, data-independent, deterministic, and safe across retries.
- Create required fixtures per test (prefer API setup over UI clicks). Always clean up created data in `finally` / teardown.

## Ask First

- Ask before applying migrations or resetting a developer's local database.
- Ask before adding tests that require live external services. Prefer stubs and metadata gates.

## Never

- Never rely on seeded/demo data being present.
- Never leave broken tests; fix them or skip with `test.skip()` + a clear reason.
- Never let a Playwright test mutate another test's data.

## Validation Commands

```bash
make test-api        # PHPUnit unit + functional
make test-e2e        # Playwright end-to-end
make test            # everything
```

Run a single PHP test:
```bash
make test-api ARG="--filter SignUpControllerTest"
```

Run a single Playwright test:
```bash
pnpm -C apps/web exec playwright test e2e/auth.spec.ts
```

---

## Two Layers of Tests

### 1. Functional tests (PHPUnit, behind the bus)

For API behaviour: pick the `WebTestCase` base + transaction rollback per test.

- Location: `apps/api/code/tests/Functional/<Context>/<Endpoint>Test.php`
- Pattern: one test class per controller action.
- DB: real PostgreSQL (`test` env). Transactional rollback via `FunctionalTestCase`.
- Auth: helper `loginAs($client, 'admin@example.com')` sets the `Authorization` header.

```php
final class CreateNoteControllerTest extends FunctionalTestCase
{
    public function testItCreatesANoteForTheAuthenticatedUser(): void
    {
        $client = static::createClient();
        $this->loginAs($client, 'user@example.com');

        $client->request('POST', '/api/notes', server: ['CONTENT_TYPE' => 'application/json'], content: json_encode([
            'title' => 'first note',
            'body'  => 'hello',
        ]));

        self::assertResponseStatusCodeSame(201);
        $payload = json_decode($client->getResponse()->getContent(), true);
        self::assertArrayHasKey('id', $payload);
    }
}
```

### 2. End-to-end tests (Playwright, browser)

For full user journeys touching the UI.

- Location: `apps/web/e2e/<area>/<flow>.spec.ts` or `apps/admin/e2e/<area>/<flow>.spec.ts`
- Pattern: one spec file per user journey.
- Helpers: `apps/web/e2e/helpers/{auth,api,fixtures}.ts`

```ts
import { test, expect } from '@playwright/test';
import { signUp } from './helpers/auth';

test('user can sign up and create a note', async ({ page }) => {
  const { email, password } = await signUp(page);
  await page.goto('/notes');
  await page.getByRole('button', { name: 'New note' }).click();
  await page.getByLabel('Title').fill('hello world');
  await page.getByRole('button', { name: 'Save' }).click();
  await expect(page.getByText('hello world')).toBeVisible();
});
```

---

## How to Create New Tests

1. Read the related spec from `.ai/specs/` (its **Integration Coverage** section lists what must exist).
2. Use the `/integration-tests` skill to generate the test scaffold from the spec or feature description.
3. Verify by running the test in isolation, then `make test`.

## Test Rules

- Use Playwright locators (`getByRole`, `getByLabel`, `getByText`) — avoid CSS selectors.
- Reference the source spec in a top comment: `// Source: .ai/specs/2026-06-12-add-notes.md`.
- Keep tests independent — each handles its own auth.
- Keep tests data-independent — no reliance on seeded records.
- Use API fixtures for setup whenever possible; reserve UI clicks for the assertion path.
- One `.spec.ts` file per user journey.
- Clean up created data in `finally`.

## Default Credentials

The Notes hello-world ships with two seeded users (created by `make seed-demo`):

| Role | Email | Password |
|------|-------|----------|
| Admin | `admin@example.com` | `secret` |
| User  | `user@example.com`  | `secret` |

Tests SHOULD create their own users via `signUp` rather than depend on these.

## Results Presentation

For headless runs (`make test-e2e`):
- Console: pass/fail summary.
- HTML report: `apps/web/playwright-report/` (open with `pnpm -C apps/web exec playwright show-report`).

For AI-driven exploratory runs, report:

| Test ID | Test Name | Status | Notes |
|---------|-----------|--------|-------|
