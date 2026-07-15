---
name: integration-tests
description: Run, create, and convert backend integration tests (PHPUnit Functional for the API) and frontend unit tests (Vitest + React Testing Library for apps/web and apps/admin). Triggers on "run integration tests", "test this feature", "create test for", "convert scenario", "integration test", "unit test".
---

# Integration & Frontend Unit Tests

Run the test suites or create new tests from a spec, scenario, or feature description.

## Workflow

### Running existing tests

```sh
make test-api              # PHPUnit unit + functional
make test-web              # Vitest (apps/web + apps/admin)
make test                  # everything (API + frontends)
```

To filter PHPUnit:
```sh
make test-api ARG="--filter ItCreatesANewUserTest"   # one scenario
make test-api ARG="--filter SignUp"                   # a use-case group
```

`make test-web` runs standalone (no postgres/api) — there's no persistent, named `web`/
`admin` container to exec into for a single file. Run one-off files the same way the
Makefile does, via an ephemeral container that reuses the cached `node_modules` volume:
```sh
docker compose --env-file .env.local -p <TEST_PROJECT_NAME> \
  -f ops/docker/docker-compose.base.yml -f ops/docker/docker-compose.test.yml \
  run --rm --no-deps web \
  sh -c 'corepack enable && corepack prepare pnpm@11.5.0 --activate && pnpm install --filter "@jperdior/web..." --filter "./packages/*" && pnpm -C apps/web exec vitest run src/app/__tests__/smoke.test.tsx'
```
Substitute `admin` / `apps/admin` for the admin app. `<TEST_PROJECT_NAME>` matches the
Makefile's derivation (`jperdior-test-<worktree-dirname, with any + sanitized to ->`).

### Creating new tests

1. **Find the source**:
   - From a spec: open the spec's **Integration Coverage** section.
   - From a scenario: read the markdown under `.ai/qa/scenarios/`.
   - From a feature description: ask the user for the behaviour to assert.

2. **Decide the test layer**:
   - **PHPUnit Functional** for API contract assertions (status codes, response shape, auth, ownership).
   - **Vitest + React Testing Library** for frontend component behaviour, presentational logic, hooks, and Server Action client wrappers.
   - Most user-visible changes need **both**: one PHPUnit scenario class per behaviour + one frontend test per non-trivial component or hook.

3. **Generate the scaffold**:
   - PHPUnit: one class per scenario, named `It<Scenario>Test`, under
     `apps/api/tests/Functional/<Context>/Presentation/Http/<UseCase>/It<Scenario>Test.php`
     (or `.../Infrastructure/Console/<Command>/It<Scenario>Test.php`). Each extends an
     abstract `Base<UseCase>Test` (holding shared `setUp()` + a default `arrange()`), which
     extends `FunctionalTestCase`. Only `It*Test` files are collected by the Functional
     test-suite — `Base*Test` bases are skipped by the `prefix="It"` config.
   - Vitest (web): colocate next to the unit being tested under `__tests__/`, e.g. `apps/web/src/app/<route>/__tests__/<Component>.test.tsx`.
   - Vitest (admin): same convention under `apps/admin/src/`.

4. **Write the test** using the templates below.

5. **Verify**: run just the new test, then `make test`.

## PHPUnit Functional Template

Every functional test is **one class per scenario**, and Arrange-Act-Assert is enforced by
the base: `FunctionalTestCase` owns a `final #[Test] testExecution()` that calls
`arrange() → act() → assert()`, all three `abstract`. You never write a test method — you
implement the three phases.

**Per-use-case base** (shared `setUp()` + default `arrange()`; HTTP goes through a page object):

```php
<?php

declare(strict_types=1);

namespace App\Tests\Functional\User\Presentation\Http\Me;

use App\Tests\Functional\FunctionalTestCase;
use App\Tests\Support\Pages\UserPage;

abstract class BaseMeTest extends FunctionalTestCase
{
    protected UserPage $page;

    protected function setUp(): void
    {
        parent::setUp();
        $this->page = $this->userPage();
    }

    protected function arrange(): void
    {
    }
}
```

**One scenario, one `final` class** (`It<Scenario>Test`), overriding only what it needs:

```php
<?php

declare(strict_types=1);

namespace App\Tests\Functional\User\Presentation\Http\Me;

final class ItReturnsMeWhenAuthenticatedTest extends BaseMeTest
{
    private string $token = '';

    protected function arrange(): void
    {
        $this->userFixture()->createOne('me@example.com', 'secretpass');
        $this->page->login('me@example.com', 'secretpass');
        $this->token = $this->page->extractToken();
    }

    protected function act(): void
    {
        $this->page->me($this->token);
    }

    protected function assert(): void
    {
        self::assertSame(200, $this->page->getStatusCode());
        self::assertSame('me@example.com', $this->page->getResponseJson()['email']);
    }
}
```

A scenario with no setup implements only `act()` and `assert()` (the base's default
`arrange()` covers it). **Application-layer handler tests** follow the same AAA shape but
have no page object: `act()` dispatches through `CommandBus`/`QueryBus`, and `assert()`
inspects repository state, the returned query DTO, or a `SpyEventBus`. **Console tests**
put the command invocation in `act()` and the exit-code/repository check in `assert()`.

## Vitest + React Testing Library Template

```tsx
import { describe, it, expect } from 'vitest';
import { render, screen } from '@testing-library/react';
import { MyComponent } from '../MyComponent';

describe('MyComponent', () => {
  it('renders the title and a primary action', () => {
    render(<MyComponent title="Hello" />);
    expect(screen.getByRole('heading', { name: 'Hello' })).toBeInTheDocument();
    expect(screen.getByRole('button', { name: 'Save' })).toBeEnabled();
  });
});
```

Each app provides a `vitest.setup.ts` that loads `@testing-library/jest-dom` matchers and mocks `next/link` and `next/navigation`. Reuse those mocks instead of duplicating per-file.

## Rules

- **One PHPUnit class per scenario**, named `It<Scenario>Test`; shared setup/arrangement lives in the abstract `Base<UseCase>Test`. Never add a `#[Test]` method or a second test method to a functional test — AAA is the only entry point.
- Fixture isolation is automatic: `FunctionalTestCase` wraps each test in a DB transaction and rolls it back in teardown. Do not write manual cleanup.
- Vitest tests must be **deterministic**: no real network, no real timers (use `vi.useFakeTimers()`), no real cookies. Mock module boundaries (`next/headers`, `@jperdior/api-client-ts`) at the test level.
- Use **role-based queries** (`getByRole`, `getByLabel`, `getByText`). Avoid `getByTestId` and CSS selectors.
- Reference the spec in a top comment when applicable.
- Colocate Vitest files with the unit they cover under `__tests__/`.
- Never leave broken tests. Skip with `it.skip()` / `markTestSkipped` + a clear reason if intentional.

## Helpers

For PHP, `apps/api/tests/Functional/FunctionalTestCase.php` exposes:
- the AAA contract: `arrange()` / `act()` / `assert()` (implement these; never a test method)
- `loginAs(string $email, string $password): string` — returns the JWT
- `userPage(): UserPage` — the HTTP page object (`tests/Support/Pages/`)
- `userFixture()` / `passwordRecoveryTokenFixture()` — data fixtures (`tests/Support/Fixtures/`)
- `postJson()`, `jsonResponse()`, `entityManager()` — low-level helpers

For Vitest, prefer plain RTL helpers (`userEvent.setup()`, `render`, `screen`). If you find yourself reaching for shared fixtures across many tests, add a `apps/<app>/src/test-utils/` directory rather than copy-pasting.

## Conditional / metadata gates

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

For Vitest:
```ts
it.skipIf(!process.env.STRIPE_SECRET_KEY)('exercises Stripe', () => { /* ... */ });
```
