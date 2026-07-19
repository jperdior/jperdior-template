# QA Testing Instructions

## Always

- Prefer Symfony **functional tests** (PHPUnit, one class per scenario named `It<Scenario>Test`) for API behaviour, under `apps/api/tests/Functional/<Context>/`.
- Prefer **Vitest + React Testing Library** for frontend component / hook behaviour, colocated next to the unit under `apps/web/src/**/__tests__/` or `apps/admin/src/**/__tests__/`.
- Keep tests independent, data-independent, deterministic, and safe across retries.
- Create required fixtures per test (prefer API setup over UI clicks for functional tests). Functional tests get automatic isolation — `FunctionalTestCase` wraps each in a DB transaction and rolls it back — so no manual data cleanup is needed.

## Ask First

- Ask before applying migrations or resetting a developer's local database.
- Ask before adding tests that require live external services. Prefer stubs and metadata gates.

## Never

- Never rely on seeded/demo data being present.
- Never leave broken tests; fix them or skip with `it.skip()` / `markTestSkipped` + a clear reason.
- Never let a test mutate another test's data or global state (DB rows, cookies, module mocks).

## Validation Commands

```bash
make test-api        # PHPUnit unit + functional
make test-web        # Vitest (apps/web + apps/admin)
make test            # everything
```

Run a single PHP test:
```bash
make test-api ARG="--filter ItCreatesANewUserTest"   # one scenario
make test-api ARG="--filter SignUp"                   # a use-case group
```

Run a single Vitest file, inside the container:
```bash
docker compose -p jperdior -f ops/docker/docker-compose.base.yml -f ops/docker/docker-compose.dev.yml \
  exec web pnpm -C apps/web exec vitest run src/app/__tests__/smoke.test.tsx
```

---

## Two Layers of Tests

### 1. API functional tests (PHPUnit, behind the bus)

For API behaviour: `FunctionalTestCase` (extends `WebTestCase`) + transactional rollback per test.

- Location: `apps/api/tests/Functional/<Context>/Presentation/Http/<UseCase>/It<Scenario>Test.php`.
- Pattern: **one class per scenario**. Arrange-Act-Assert is enforced — `FunctionalTestCase`
  owns `final #[Test] testExecution()` → `arrange()/act()/assert()` (all abstract). You never
  write a test method. Shared setup + a default `arrange()` live in an abstract `Base<UseCase>Test`.
- Discovery: the Functional suite is scoped `prefix="It"`, so only `It*Test` scenarios run;
  `Base*Test` bases are not collected.
- DB: real PostgreSQL (`test` env). Transactional rollback via `FunctionalTestCase` — no manual cleanup.
- HTTP: go through a page object (`tests/Support/Pages/`); data via fixtures (`tests/Support/Fixtures/`).
- Auth: `loginAs(string $email, string $password): string` returns a JWT to pass as a Bearer token.

```php
// Base<UseCase>Test — shared setup + default arrange
abstract class BaseCreateNoteTest extends FunctionalTestCase
{
    protected NotePage $page;

    protected function setUp(): void
    {
        parent::setUp();
        $this->page = $this->notePage();
    }

    protected function arrange(): void
    {
    }
}

// It<Scenario>Test — exactly one scenario
final class ItCreatesANoteForTheAuthenticatedUserTest extends BaseCreateNoteTest
{
    private string $token = '';

    protected function arrange(): void
    {
        $this->userFixture()->createOne('user@example.com', 'secretpass');
        $this->token = $this->loginAs('user@example.com', 'secretpass');
    }

    protected function act(): void
    {
        $this->page->create('first note', 'hello', $this->token);
    }

    protected function assert(): void
    {
        self::assertSame(201, $this->page->getStatusCode());
        self::assertArrayHasKey('id', $this->page->getResponseJson());
    }
}
```

Application-layer handler tests use the same AAA shape but dispatch through the bus in
`act()` (no page object); console tests run the command in `act()`.

### 2. Frontend unit tests (Vitest + React Testing Library, jsdom)

For component behaviour, presentational logic, hooks, and Server Action wrappers.

- Location: `apps/web/src/**/__tests__/<Subject>.test.tsx` or `apps/admin/src/**/__tests__/<Subject>.test.tsx`
- Pattern: one test file per non-trivial component, hook, or pure module.
- Environment: jsdom. `next/link` and `next/navigation` are mocked in each app's `vitest.setup.ts`.

```tsx
import { describe, it, expect } from 'vitest';
import { render, screen } from '@testing-library/react';
import { PaginationControls } from '../PaginationControls';

describe('PaginationControls', () => {
  it('shows the Next link when there are more rows', () => {
    render(<PaginationControls total={42} offset={0} limit={10} />);
    expect(screen.getByRole('link', { name: 'Next' })).toHaveAttribute('href', '?offset=10');
  });
});
```

---

## How to Create New Tests

1. Read the related spec from `.ai/specs/` (its **Integration Coverage** section lists what must exist).
2. Use the `/integration-tests` skill to generate the test scaffold from the spec or feature description.
3. Verify by running the test in isolation, then `make test`.

## Test Rules

- Use role-based queries (`getByRole`, `getByLabel`, `getByText`) — avoid `getByTestId` and CSS selectors.
- Reference the source spec in a top comment when applicable: `// Source: .ai/specs/2026-06-12-add-notes.md`.
- Keep tests independent — each handles its own setup in `arrange()`.
- Keep tests data-independent — no reliance on seeded records.
- Use API fixtures for setup whenever possible in functional tests; reserve UI clicks for the assertion path.
- One PHPUnit class per scenario (`It<Scenario>Test`), extending a `Base<UseCase>Test`; never add a `#[Test]` method. Colocate Vitest files next to the unit they cover under `__tests__/`.
- Functional-test data is discarded by transactional rollback — no manual `finally`/teardown cleanup.

## Default Credentials

The hello-world ships with two seeded users (created by `make seed-demo`):

| Role | Email | Password |
|------|-------|----------|
| Admin | `admin@example.com` | `secret` |
| User  | `user@example.com`  | `secret` |

Tests SHOULD create their own users via the API rather than depend on these.

## Results Presentation

For AI-driven exploratory runs, report:

| Test ID | Test Name | Status | Notes |
|---------|-----------|--------|-------|
