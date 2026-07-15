# Align API Functional Tests with the Reference AAA Pattern

## TLDR

Port the docplanner `integrations-connect-app` functional-test conventions into this template so every API functional test is **one class per test case** with a **structurally enforced Arrange-Act-Assert** contract. The template already has ~80% of this (transactional-rollback base, per-endpoint bases, page objects, fixtures); this spec centralizes the AAA contract into `FunctionalTestCase`, relocates test support to `tests/Support/{Fixtures,Pages}`, renames per-use-case bases to `Base<UseCase>Test`, splits the one remaining multi-method console test, and rewrites the stale agentic docs/skills that still teach the old one-class-many-methods style.

## Overview

The reference project enforces a rigid, highly legible functional-test shape: a shared `FunctionalTestCase` (extends `WebTestCase`) opens a DB transaction per test and rolls it back in teardown, and declares `runTestCase()` + `abstract arrange()/act()/assert()`. Each concrete test is a `final` class representing exactly one scenario; shared setup and default arrangement live in an abstract `Base<UseCase>Test`. HTTP interaction is encapsulated in **page objects** under `tests/Support/Pages`, and test data in **fixtures** under `tests/Support/Fixtures`.

This template was recently refactored (PR #44) toward that shape, but three things diverge from the reference and the agentic documentation was never updated to match the new code — the docs still show the old `testItDoesX()` / `testItDoesY()` many-methods-per-class style, which now actively contradicts the codebase. New contexts scaffolded from those skills would regress to the old pattern.

## Problem Statement

Concrete pain:

1. **AAA is not enforced, only conventional.** `runTestCase()`-equivalent logic (`arrange(); act(); assert();`) is copy-pasted into every `*ControllerTestCase`. A new per-endpoint base can silently omit it, or add extra `#[Test]` methods, and nothing stops it. The contract should be impossible to bypass.
2. **The agentic docs lie about the codebase.** `.ai/qa/AGENTS.md` and `.ai/skills/integration-tests/SKILL.md` show single classes with multiple `testItReturns...()` methods and no AAA phases. `add-command`/`add-query`/`add-route` point to `<Verb><Aggregate>ControllerTest.php` (one-class-many-tests). These are the files the harness reads to scaffold new tests — they will reintroduce the old pattern.
3. **Naming/layout drift from the reference.** Support code lives at `tests/Functional/Support/{Fixture,Page}` (singular, nested) rather than the reference `tests/Support/{Fixtures,Pages}` (plural, top-level); per-endpoint bases are `<UseCase>ControllerTestCase` rather than `Base<UseCase>Test`; the entry method is `test()` rather than `testExecution()`.
4. **One multi-method test remains.** `SeedAdminCommandTest` bundles several console scenarios in one class — the exception to one-class-per-case, and it will not compile once `testExecution()` is `final` in the base.

## Proposed Solution

Adopt the reference conventions verbatim for structure and naming, while keeping the template's own realities (JWT auth via `loginAs`, instance fixtures with DI for password hashing, `App\Tests\` PSR-4 namespace).

### Centralized, un-bypassable AAA contract

`apps/api/tests/Functional/FunctionalTestCase.php` gains the contract (keeping all existing helpers — `postJson`, `jsonResponse`, `loginAs`, `userPage`, `userFixture`, `passwordRecoveryTokenFixture`, `entityManager`):

```php
abstract class FunctionalTestCase extends WebTestCase
{
    // ... existing setUp()/tearDown() transaction handling and helpers ...

    #[Test]
    final public function testExecution(): void
    {
        $this->runTestCase();
    }

    public function runTestCase(): void
    {
        $this->arrange();
        $this->act();
        $this->assert();
    }

    abstract protected function arrange(): void;
    abstract protected function act(): void;
    abstract protected function assert(): void;
}
```

Because `testExecution()` is `final`, no subclass can add a second `#[Test]` method path through the base — every functional test is exactly one AAA case.

### Per-use-case base classes

Each use-case directory gets an abstract `Base<UseCase>Test extends FunctionalTestCase` that:
- performs shared `setUp()` (e.g. builds the page object),
- provides a **default `arrange(): void {}`** so single-scenario leaves override only `act()`/`assert()`,
- holds any shared arrangement (like the reference's `BaseGetUserNotificationsTest`).

Leaf scenario classes stay `final`, extend the base, and override `arrange()` (calling `parent::arrange()` when extending shared setup), `act()`, `assert()`.

### Relocation & renaming (match reference exactly)

| From | To |
|------|----|
| `tests/Functional/Support/Fixture/` | `tests/Support/Fixtures/` |
| `tests/Functional/Support/Page/` | `tests/Support/Pages/` |
| `App\Tests\Functional\Support\Fixture\*` | `App\Tests\Support\Fixtures\*` |
| `App\Tests\Functional\Support\Page\*` | `App\Tests\Support\Pages\*` |
| `SignUpControllerTestCase` | `BaseSignUpTest` |
| `LoginControllerTestCase` | `BaseLoginTest` |
| `MeControllerTestCase` | `BaseMeTest` |
| `RequestPasswordRecoveryControllerTestCase` | `BaseRequestPasswordRecoveryTest` |
| `ResetPasswordWithTokenControllerTestCase` | `BaseResetPasswordWithTokenTest` |
| (new) | `BaseAdminDeleteUserTest`, `BaseSeedAdminTest` |
| `test()` entry method | removed (inherited `testExecution()`) |

`App\Tests\` maps to `tests/`, so `tests/Support/Fixtures/UserFixture.php` = `App\Tests\Support\Fixtures\UserFixture`. Fixtures remain **instance** classes with constructor DI (repository + hasher); only their namespace/location changes.

### Split the console multi-method test

`SeedAdminCommandTest` is decomposed into `tests/Functional/User/Infrastructure/Console/SeedAdmin/` with `BaseSeedAdminTest` (shares `CommandTester` setup) and one `final` `It…Test` per scenario, each in AAA form (arrange: seed state; act: run the command; assert: exit code + repository state).

## Architecture

- **Bounded context(s) affected**: none at the domain level — this is test-infrastructure + documentation only. Test *subjects* remain the `User` context and `Shared`.
- **New aggregates / value objects**: none.
- **Buses used**: none changed. Tests continue to exercise controllers → bus → handlers end-to-end.
- **Cross-context interaction**: none.
- **Layers touched**: `apps/api/tests/**` (structure), `.ai/**` and `apps/api/AGENTS.md` (docs). No `src/**` production code changes.

## Data Models

N/A — no aggregates, persistence models, or migrations. Fixtures continue to construct the existing `User` aggregate and `PasswordRecoveryToken` via their existing constructors/factories.

## API Contracts

N/A — no routes added, removed, or changed. No OpenAPI regeneration needed (`make gen-api` untouched; `openapi-drift` unaffected).

## Frontend Plan

N/A — backend test infrastructure only.

## Reference Pattern Anatomy (what "done" looks like)

Centralized base + per-use-case base + leaf scenario:

```php
// tests/Functional/User/Presentation/Http/SignUp/BaseSignUpTest.php
abstract class BaseSignUpTest extends FunctionalTestCase
{
    protected UserPage $page;

    protected function setUp(): void
    {
        parent::setUp();
        $this->page = $this->userPage();
    }

    protected function arrange(): void {}   // default; leaves override as needed
}

// tests/Functional/User/Presentation/Http/SignUp/ItCreatesANewUserTest.php
final class ItCreatesANewUserTest extends BaseSignUpTest
{
    protected function act(): void
    {
        $this->page->signUp('newuser@example.com', 'secretpass');
    }

    protected function assert(): void
    {
        self::assertSame(201, $this->page->getStatusCode());
        self::assertArrayHasKey('id', $this->page->getResponseJson());
    }
}
```

## Phasing

Each phase ends with `make lint-api && make test-api` green. All phases ship in a single PR on this `feat-aaa-functional-tests` branch (spec is the first commit).

| Phase | Goal | Deliverable |
|-------|------|-------------|
| 0 | **Spec committed.** | This file on the branch. |
| 1 | **Centralize the AAA contract + relocate support.** | `FunctionalTestCase` gains `testExecution()`/`runTestCase()`/abstract `arrange/act/assert`. Move `Support/Fixture`→`Support/Fixtures`, `Support/Page`→`Support/Pages` (via `git mv`), update namespaces + the `userPage()/userFixture()/passwordRecoveryTokenFixture()` factory imports. Rename per-use-case bases to `Base<UseCase>Test`, drop their duplicated `test()`/AAA declarations, add default `arrange()`. Update every leaf `It…Test` to extend the renamed base and drop now-inherited members. `make test-api` green (same test count minus the console split, done in Phase 2). |
| 2 | **Fold the exceptions into the pattern.** | Give `AdminDeleteUser` a `BaseAdminDeleteUserTest` and make `ItRejectsSelfDeletionTest` extend it. Split `SeedAdminCommandTest` into `Console/SeedAdmin/BaseSeedAdminTest` + one `It…Test` per scenario. `make test-api` green; total scenario count ≥ original assertion coverage. |
| 3 | **Sync all agentic docs/skills to the new pattern.** | Rewrite the test sections of `apps/api/AGENTS.md`, `.ai/qa/AGENTS.md`, `.ai/skills/integration-tests/SKILL.md`, and the test-scaffolding steps in `.ai/skills/add-command`, `add-query`, `add-route`. Every code sample uses one-class-per-case + `Base<UseCase>Test` + `arrange/act/assert` + `tests/Support/{Fixtures,Pages}`. Present-tense, prescriptive; no "previously/now" narration. `make lint` + `make test` green. |

## Risks & Impact Review

| Risk | Severity | Affected area | Mitigation | Residual |
|------|----------|---------------|------------|----------|
| `final testExecution()` collides with an existing `#[Test]` method in a subclass | Medium | All functional tests | Phase 1 removes per-base `test()` methods; Phase 2 splits the only multi-method test. `phpunit` fails loudly on collision, caught by the gate. | Low |
| Namespace/PSR-4 break after moving `Support/*` | Medium | Autoload | `App\Tests\` already maps to `tests/`; new paths resolve. `make lint-api` (phpstan) + `make test-api` catch any stale `use`. | Low |
| Splitting `SeedAdminCommandTest` drops a scenario | Medium | Console coverage | Enumerate original methods first; assert 1:1 mapping to new classes; keep assertions verbatim. | Low |
| Abstract `arrange()` forces boilerplate in simple leaves | Low | DX | Per-use-case `Base<UseCase>Test` provides a default empty `arrange()`; leaves override only when needed. | None |
| Docs drift again | Low | Future scaffolding | Phase 3 makes docs match code exactly; `/code-review` Standards axis checks doc/code consistency. | Low |

## Integration Coverage

This change **is** test infrastructure — its own acceptance criterion is that the full existing functional suite still passes under the new shape, with no loss of scenario coverage. No new behaviour is added, so no new assertions are required beyond the console split (which preserves existing assertions across more classes).

| Test ID | Type | Path | Asserts |
|---------|------|------|---------|
| TC-1 | PHPUnit Functional | `apps/api/tests/Functional/User/Presentation/Http/**` | All existing SignUp/Login/Me/RequestPasswordRecovery/ResetPasswordWithToken/AdminDeleteUser scenarios pass unchanged after refactor. |
| TC-2 | PHPUnit Functional | `apps/api/tests/Functional/User/Infrastructure/Console/SeedAdmin/**` | Each former `SeedAdminCommandTest` method survives as its own AAA `It…Test`, same exit-code/repository assertions. |
| TC-3 | Structural (phpstan/phpunit) | `apps/api/tests/Functional/FunctionalTestCase.php` | `testExecution()` is `final`; `arrange/act/assert` abstract; every concrete test extends a `Base<UseCase>Test`. |

## Backward Compatibility

- [x] No removed/renamed event IDs
- [x] No removed/renamed API routes
- [x] No removed response fields
- [x] No removed DB columns
- [x] No production `src/**` change — this is test + docs only. Test class renames are internal (`App\Tests\**`), not a public contract.
- [x] Deprecation bridge: N/A (no external consumers of test classes).

## Final Compliance Report

(Filled in at the end. See `references/compliance-gate.md`.)

## Changelog

| Date | Change |
|------|--------|
| 2026-07-15 | Spec drafted. |
