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

To run a single Playwright spec, exec into the running web container:
```sh
docker compose -p jperdior -f ops/docker/docker-compose.base.yml -f ops/docker/docker-compose.dev.yml exec web pnpm -C apps/web exec playwright test e2e/auth.spec.ts
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

namespace App\Tests\Functional\User\Presentation\Http;

use App\Tests\Functional\FunctionalTestCase;

final class GetMeControllerTest extends FunctionalTestCase
{
    public function testItReturnsTheAuthenticatedUser(): void
    {
        $client = static::createClient();
        $token  = $this->loginAs('user@example.com', 'secret');

        $client->request(
            'GET',
            '/api/me',
            server: ['HTTP_AUTHORIZATION' => 'Bearer ' . $token],
        );

        self::assertResponseStatusCodeSame(200);
        $payload = json_decode($client->getResponse()->getContent(), true);
        self::assertSame('user@example.com', $payload['email']);
    }

    public function testItReturns401WhenUnauthenticated(): void
    {
        $client = static::createClient();
        $client->request('GET', '/api/me');

        self::assertResponseStatusCodeSame(401);
    }
}
```

## Playwright e2e Template

```ts
import { test, expect } from '@playwright/test';
import { signUp } from './helpers/auth';

// Source: .ai/specs/2026-06-12-<feature>.md

test.describe('<Feature>: <journey name>', () => {
  test('a signed-up user can <do the thing>', async ({ page }) => {
    const { email } = await signUp(page);

    await page.goto('/<route>');
    await page.getByRole('button', { name: '<action>' }).click();
    await page.getByLabel('<field>').fill('<value>');
    await page.getByRole('button', { name: 'Save' }).click();

    await expect(page.getByRole('heading', { name: '<expected>' })).toBeVisible();
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
- `signUp(page, options?)` — signs up a unique user via the UI and returns `{ email, password }`. Lands on `/dashboard` after signup.

Add more helpers to `apps/web/e2e/helpers/` as journeys grow (e.g. `signIn`, `signInAsAdmin`).

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
