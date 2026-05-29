---
name: integration-tests
description: Run, create, and convert integration tests (PHPUnit Functional for the API, Playwright e2e for the frontends). Triggers on "run integration tests", "test this feature", "create test for", "convert scenario", "integration test".
---

# Integration Tests

Run the integration suite or create new tests from a spec, scenario, or feature description.

## Workflow

### Running existing tests

```sh
make test-api        # PHPUnit unit + functional
make test-e2e        # Playwright (web)
make test            # everything
```

To filter PHPUnit:
```sh
make test-api ARG="--filter SignUpControllerTest"
```

To run a single Playwright spec:
```sh
pnpm -C apps/web exec playwright test e2e/auth.spec.ts
```

### Creating new tests

1. **Find the source**:
   - From a spec: open the spec's **Integration Coverage** section.
   - From a scenario: read the markdown under `.ai/qa/scenarios/`.
   - From a feature description: ask the user for the user journey.

2. **Decide the test layer**:
   - **PHPUnit Functional** for API contract assertions (status codes, response shape, auth, ownership).
   - **Playwright e2e** for user journeys that go through the UI.
   - Most behaviours need **both**: one PHPUnit test per controller action + one Playwright test per journey.

3. **Generate the scaffold**:
   - PHPUnit: place under `apps/api/tests/Functional/<Context>/<Controller>Test.php`. Extend `FunctionalTestCase`.
   - Playwright: place under `apps/web/e2e/<area>/<flow>.spec.ts`. Import helpers from `apps/web/e2e/helpers/`.

4. **Walk the flow** (Playwright only): with `make start` running, navigate the UI and confirm selectors (`getByRole` / `getByLabel` / `getByText`).

5. **Write the test** using the templates below.

6. **Verify**: run just the new test, then `make test`.

## PHPUnit Functional Template

```php
<?php

declare(strict_types=1);

namespace App\Tests\Functional\Note\Presentation\Http;

use App\Tests\Functional\FunctionalTestCase;

final class CreateNoteControllerTest extends FunctionalTestCase
{
    public function testItCreatesANoteForTheAuthenticatedUser(): void
    {
        $client = static::createClient();
        $token  = $this->loginAs('user@example.com', 'secret');

        $client->request(
            'POST',
            '/api/notes',
            server: [
                'CONTENT_TYPE'        => 'application/json',
                'HTTP_AUTHORIZATION'  => 'Bearer ' . $token,
            ],
            content: json_encode([
                'title' => 'first note',
                'body'  => 'hello',
            ]),
        );

        self::assertResponseStatusCodeSame(201);
        $payload = json_decode($client->getResponse()->getContent(), true);
        self::assertArrayHasKey('id', $payload);
        self::assertSame('first note', $payload['title']);
    }

    public function testItReturns401WhenUnauthenticated(): void
    {
        $client = static::createClient();
        $client->request('POST', '/api/notes', content: '{}');

        self::assertResponseStatusCodeSame(401);
    }
}
```

## Playwright e2e Template

```ts
import { test, expect } from '@playwright/test';
import { signUp } from './helpers/auth';

// Source: .ai/specs/2026-06-12-add-notes.md

test.describe('Notes: create + see', () => {
  test('a signed-up user can create and see their note', async ({ page }) => {
    const { email } = await signUp(page);

    await page.goto('/notes');
    await page.getByRole('button', { name: 'New note' }).click();
    await page.getByLabel('Title').fill('Hello world');
    await page.getByLabel('Body').fill('My first note');
    await page.getByRole('button', { name: 'Save' }).click();

    await expect(page.getByRole('heading', { name: 'Hello world' })).toBeVisible();
    await expect(page.getByText('My first note')).toBeVisible();
  });
});
```

## Rules

- Each test handles its own auth and fixture lifecycle.
- Each test cleans up created data in `finally` / teardown.
- Use Playwright **role-based locators** (`getByRole`, `getByLabel`, `getByText`). Avoid CSS selectors.
- Reference the spec in a top comment when applicable.
- One `.spec.ts` file per journey. One PHPUnit test class per controller.
- Never leave broken tests. Skip with `test.skip()` + clear reason if intentional.

## Helpers

`apps/web/e2e/helpers/auth.ts` exposes:
- `signUp(page)` — creates a unique user via the API and returns `{ email, password, token }`
- `signIn(page, { email, password })` — performs UI login
- `signInAsAdmin(page)` — convenience for admin journeys

`apps/web/e2e/helpers/api.ts` exposes:
- `apiRequest(method, path, options)` — authenticated fetch
- `getAuthToken(credentials)` — obtain a JWT via `/auth/login`

For PHP, `apps/api/tests/Functional/FunctionalTestCase.php` exposes:
- `loginAs(string $email, string $password): string` — returns the JWT
- `withinTransaction(callable $fn)` — opt-in transactional wrapper

## Conditional / metadata gates

If a test requires an env var (e.g. an external API key), declare it at the top:

```ts
test.skip(!process.env.STRIPE_SECRET_KEY, 'requires STRIPE_SECRET_KEY');
```

For PHP:
```php
protected function setUp(): void
{
    parent::setUp();
    if (!getenv('STRIPE_SECRET_KEY')) {
        self::markTestSkipped('requires STRIPE_SECRET_KEY');
    }
}
```
