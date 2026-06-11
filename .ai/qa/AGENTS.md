# QA Testing Instructions

## Always

- Prefer Symfony **functional tests** (PHPUnit + `WebTestCase`) for API behaviour, under `apps/api/tests/Functional/<Context>/`.
- Prefer **Vitest + React Testing Library** for frontend component / hook behaviour, colocated next to the unit under `apps/web/src/**/__tests__/` or `apps/admin/src/**/__tests__/`.
- Keep tests independent, data-independent, deterministic, and safe across retries.
- Create required fixtures per test (prefer API setup over UI clicks for functional tests). Always clean up created data in `finally` / teardown.

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
make test-api ARG="--filter SignUpControllerTest"
```

Run a single Vitest file, inside the container:
```bash
docker compose -p jperdior -f ops/docker/docker-compose.base.yml -f ops/docker/docker-compose.dev.yml \
  exec web pnpm -C apps/web exec vitest run src/app/__tests__/smoke.test.tsx
```

---

## Two Layers of Tests

### 1. API functional tests (PHPUnit, behind the bus)

For API behaviour: `WebTestCase` base + transactional rollback per test.

- Location: `apps/api/tests/Functional/<Context>/<Endpoint>Test.php`
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
- Keep tests independent — each handles its own setup.
- Keep tests data-independent — no reliance on seeded records.
- Use API fixtures for setup whenever possible in functional tests; reserve UI clicks for the assertion path.
- One PHPUnit test class per controller. Colocate Vitest files next to the unit they cover under `__tests__/`.
- Clean up created data in `finally` / teardown.

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
