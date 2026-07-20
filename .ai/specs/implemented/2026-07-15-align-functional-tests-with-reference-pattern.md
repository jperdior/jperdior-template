# Align API Functional Tests with the Reference AAA Pattern

## TLDR

Port the docplanner `integrations-connect-app` functional-test conventions into this template so every API functional test is **one class per test case** with a **structurally enforced Arrange-Act-Assert** contract. The template already has ~80% of this (transactional-rollback base, per-endpoint bases, page objects, fixtures); this spec centralizes the AAA contract into `FunctionalTestCase`, relocates test support to `tests/Support/{Fixtures,Pages}`, renames per-use-case bases to `Base<UseCase>Test`, splits the one remaining multi-method console test, and rewrites the stale agentic docs/skills that still teach the old one-class-many-methods style.

## Overview

The reference project enforces a rigid, highly legible functional-test shape: a shared `FunctionalTestCase` (extends `WebTestCase`) opens a DB transaction per test and rolls it back in teardown, and declares `runTestCase()` + `abstract arrange()/act()/assert()`. Each concrete test is a `final` class representing exactly one scenario; shared setup and default arrangement live in an abstract `Base<UseCase>Test`. HTTP interaction is encapsulated in **page objects** under `tests/Support/Pages`, and test data in **fixtures** under `tests/Support/Fixtures`.

This template was recently refactored (PR #44) toward that shape, but three things diverge from the reference and the agentic documentation was never updated to match the new code — the docs still show the old `testItDoesX()` / `testItDoesY()` many-methods-per-class style, which now actively contradicts the codebase. New contexts scaffolded from those skills would regress to the old pattern.

## Problem Statement

Concrete pain:

1. **AAA is not enforced, only conventional.** `runTestCase()`-equivalent logic (`arrange(); act(); assert();`) is copy-pasted into every `*ControllerTestCase`. A new per-endpoint base can silently omit it, or add extra `#[Test]` methods, and nothing stops it. The contract should be impossible to bypass.
2. **The agentic docs contradict the codebase.** `.ai/qa/AGENTS.md` and `.ai/skills/integration-tests/SKILL.md` show single classes with multiple `testItReturns...()` methods and no AAA phases. `.ai/skills/add-route` points to a one-class-many-tests `<Verb><Aggregate>ControllerTest.php`; `.ai/skills/add-command` / `add-query` point to `<Verb>{Command,Query}HandlerTest.php` under `Application/` with **no** AAA / one-class-per-case guidance and **no story for how AAA maps onto a bus-exercising handler test** (no page object, no HTTP `act()`). `docs/adding-a-bounded-context.md`, `apps/api/src/User/AGENTS.md`, and `.ai/skills/scaffold-bounded-context/SKILL.md` likewise teach or hardcode the old shape and paths. Two skill/doc examples run `--filter SignUpControllerTest`, a class name this refactor deletes — post-rename that filter silently matches nothing. These are the files the harness reads to scaffold new tests; left stale they reintroduce the old pattern.
3. **Naming/layout drift from the reference.** Support code lives at `tests/Functional/Support/{Fixture,Page}` (singular, nested) rather than the reference `tests/Support/{Fixtures,Pages}` (plural, top-level); per-endpoint bases are `<UseCase>ControllerTestCase` rather than `Base<UseCase>Test`; the entry method is `test()` rather than `testExecution()`; and one leaf (`MeRequiresAuthTest`) does not follow the `It…Test` convention.
4. **Two tests resist the single-case shape.** `SeedAdminCommandTest` bundles three console scenarios in one class (one of which — idempotency — *asserts* by running the command twice), and `AdminDeleteUser/ItRejectsSelfDeletionTest` extends `FunctionalTestCase` directly with a plain `testAdminCannotDeleteTheirOwnAccount()` method (no `#[Test]`, no AAA). Both must be reshaped once `testExecution()` is `final` in the base.

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

### Test discovery: `It*Test` scenarios, `Base*Test` bases

Naming the abstract bases `Base<UseCase>Test` makes them match PHPUnit's default `*Test.php` collection, and PHPUnit 11 emits a "class is abstract" **runner warning** for each — which it treats as a non-zero exit (independent of `failOnWarning`), breaking the gate. Since every concrete scenario is named `It…Test` and every base `Base…Test`, the Functional test-suite is scoped to `prefix="It"`:

```xml
<testsuite name="Functional">
    <directory prefix="It" suffix="Test.php">tests/Functional</directory>
</testsuite>
```

PHPUnit then collects only the concrete `It*Test` scenarios and never instantiates the abstract bases or `FunctionalTestCase`. This makes the "scenarios are `It…Test`" convention **load-bearing**: a functional test not prefixed `It` is silently not collected — Phase 3 docs state this explicitly.

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
| `MeRequiresAuthTest` (leaf) | `ItRequiresAuthTest` |
| `test()` entry method | removed (inherited `testExecution()`) |

The Support relocation touches a bounded set: **3 namespace declarations** (the moved files) + **9 `use` statements across 7 files** (`FunctionalTestCase` ×3; `ResetPasswordWithToken` base ×2; `RequestPasswordRecovery` base ×2; `SignUp`/`Me`/`Login` bases ×1 each). `phpstan` (`make lint-api`) flags any stale `use`. No `src/**` reference exists (grep-clean, aside from one prose comment in `ResetPasswordWithTokenUseCase.php`). `App\Tests\` maps to `tests/`, and the Support classes carry no `#[Test]`, so moving them out of the `tests/Functional` testsuite directory cannot change discovery.

`App\Tests\` maps to `tests/`, so `tests/Support/Fixtures/UserFixture.php` = `App\Tests\Support\Fixtures\UserFixture`. Fixtures remain **instance** classes with constructor DI (repository + hasher); only their namespace/location changes.

### Split the console multi-method test

`SeedAdminCommandTest` is decomposed into `tests/Functional/User/Infrastructure/Console/SeedAdmin/` with `BaseSeedAdminTest` (holds the shared `CommandTester` setup + private `runSeed()`/`users()` helpers + a default empty `arrange()`) and one `final` `It…Test` per scenario, each in AAA form (arrange: seed state; act: run the command; assert: exit code + repository state). The 1:1 target mapping:

| Current method | New `final` class | Notes |
|----------------|-------------------|-------|
| `testItCreatesAndPromotesTheAdminWhenMissing()` | `ItCreatesAndPromotesTheAdminWhenMissingTest` | act runs seed once; assert SUCCESS + `ROLE_ADMIN`. |
| `testItIsIdempotent()` | `ItIsIdempotentTest` | **act runs seed twice**, storing both exit codes in members; assert **both** are SUCCESS. Collapsing to one run silently drops the idempotency assertion. |
| `testItPromotesAnExistingNonAdminUser()` | `ItPromotesAnExistingNonAdminUserTest` | arrange seeds an existing non-admin via `userFixture()`; assert SUCCESS + `ROLE_ADMIN`. Keep assertions verbatim — do not "improve" to assert password-preservation that isn't asserted today. |

`AdminDeleteUser/ItRejectsSelfDeletionTest` is a **direct-extender leaf** (not merely a base to create): its body — create admin, `loginAs`, DELETE self, and the three assertions on the 409 status / `code` / `message` — must be re-expressed as `arrange()`/`act()`/`assert()` under a new `BaseAdminDeleteUserTest`, preserving all three assertions verbatim.

### Handler (Application-layer) tests and the AAA shape

Page objects are the **`act()` for HTTP/Presentation tests only**. The AAA contract itself is transport-agnostic: `act()` is "invoke the unit under test," which for a Console test is `runSeed()` and for an `Application/` handler test is *dispatch the command/query through the bus*. So `add-command` / `add-query` handler tests still extend `FunctionalTestCase`, still use `arrange/act/assert`, but their `act()` dispatches via `CommandBus`/`QueryBus` (no page object), and `assert()` inspects repository state, the query result DTO, or a `SpyEventBus`. Phase 3 makes this explicit so those skills stop implying a controller/page-object shape where none applies.

### `MeRequiresAuthTest` naming

Renamed to `ItRequiresAuthTest` in Phase 1 so every leaf follows the `It…Test` convention uniformly (matching the reference).

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

Coverage-preservation gate (run at every phase boundary): capture `vendor/bin/phpunit --testsuite functional --list-tests` and the summary `Assertions: N` line **before** Phase 1. After each phase assert (a) `errors: 0` — an errored test (e.g. PSR-4/abstract-instantiation break) is a *dropped* test, not a failure, so count **tests run**, not just green; (b) assertion total ≥ baseline; (c) after Phase 2, `grep -rn "#\[Test\]\|public function test" apps/api/tests/Functional` returns **only** `FunctionalTestCase::testExecution`.

| Phase | Goal | Deliverable |
|-------|------|-------------|
| 0 | **Spec committed.** | This file on the branch. |
| 1 | **Centralize the AAA contract + relocate support.** | `FunctionalTestCase` gains `#[Test] final testExecution()`/`runTestCase()`/abstract `arrange/act/assert`. `git mv` `Support/Fixture`→`Support/Fixtures`, `Support/Page`→`Support/Pages`; rewrite the 3 namespace declarations + 9 `use` statements (see relocation note). Rename the 5 per-use-case bases to `Base<UseCase>Test`, delete their duplicated `#[Test] test()`/AAA declarations, keep the default `arrange()` (and the password bases' `$tokens` member). Rename leaf `MeRequiresAuthTest`→`ItRequiresAuthTest`. Update every leaf to extend the renamed base and drop now-inherited members. `make lint-api && make test-api` green (test-run count unchanged; console split lands in Phase 2). |
| 2 | **Fold the two exceptions into the pattern.** | Add `BaseAdminDeleteUserTest` (with default empty `arrange()`); convert the direct-extender `ItRejectsSelfDeletionTest` from its plain `testAdminCannotDeleteTheirOwnAccount()` into AAA, preserving all 3 assertions. Split `SeedAdminCommandTest` into `Console/SeedAdmin/BaseSeedAdminTest` + the 3 `It…Test` classes per the mapping table; `ItIsIdempotentTest` must run the command **twice** and assert both exit codes. `make test-api` green; assertion total ≥ baseline; final grep gate passes. |
| 3 | **Sync every stale agentic doc/skill to the new pattern.** | Rewrite the test sections of: `apps/api/AGENTS.md`; `.ai/qa/AGENTS.md`; `.ai/skills/integration-tests/SKILL.md`; the test-scaffolding steps of `.ai/skills/add-command`, `add-query`, `add-route`; `.ai/skills/scaffold-bounded-context/SKILL.md` (highest-leverage — it scaffolds the old `<Aggregate>Test.php` shape); `docs/adding-a-bounded-context.md` (Task-Router-linked, teaches "one test class per controller action"); and `apps/api/src/User/AGENTS.md` (hardcodes the old `tests/Functional/Support/Fixture/…` path). Replace both stale `--filter SignUpControllerTest` examples with a valid filter (e.g. `--filter ItCreatesANewUserTest` or `--filter SignUp`). Every code sample uses one-class-per-case + `Base<UseCase>Test`/`It…Test` + `arrange/act/assert` + `tests/Support/{Fixtures,Pages}`; handler-test samples dispatch through the bus with no page object (see Handler-tests note). Present-tense, prescriptive; no "previously/now" narration. **Do not edit** `.ai/specs/implemented/**` (historical). `make lint && make test` green. |

Out of scope (documented, not changed): `.ai/skills/fix/SKILL.md` and `spec-writing/references/spec-template.md` use generic `tests/Functional/…` placeholders with no pattern teaching; `apps/api/src/User/AGENTS.md`'s `--filter User` still resolves by namespace. The Unit test `SeedAdminCommandProdGuardTest` stays under `tests/Unit` untouched (this refactor is Functional-only).

## Risks & Impact Review

| Risk | Severity | Affected area | Mitigation | Residual |
|------|----------|---------------|------------|----------|
| `final testExecution()` collides with an existing `#[Test]` method in a subclass | Medium | All functional tests | Phase 1 removes per-base `test()` methods; Phase 2 splits the only multi-method test. `phpunit` fails loudly on collision, caught by the gate. | Low |
| Namespace/PSR-4 break after moving `Support/*` | Medium | Autoload | `App\Tests\` already maps to `tests/`; new paths resolve. `make lint-api` (phpstan) + `make test-api` catch any stale `use`. | Low |
| Splitting `SeedAdminCommandTest` drops a scenario | High | Console coverage | 1:1 mapping table (3 classes); `ItIsIdempotentTest` keeps **both** `runSeed` calls asserted; coverage-preservation gate compares assertion totals. | Low |
| Direct-extender `ItRejectsSelfDeletionTest` overlooked (looks like an ordinary leaf) | High | Admin-delete coverage | Phase 2 explicitly converts it to AAA under `BaseAdminDeleteUserTest`, preserving all 3 assertions. | Low |
| New bases omit the default `arrange()`, breaking no-arrange leaves | Medium | `BaseAdminDeleteUserTest`, `BaseSeedAdminTest` | Both new bases must declare `protected function arrange(): void {}`; abstract-method instantiation error caught by `make test-api`. | Low |
| Stale `--filter SignUpControllerTest` silently matches nothing | Low | Docs/skills | Phase 3 replaces with a valid filter. | None |
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

- [x] **Boundary integrity** — test-only + docs change; no `src/**` touched, no cross-context imports introduced.
- [x] **Bus discipline** — unchanged; controllers still dispatch through the bus. Handler-test guidance dispatches via `CommandBus`/`QueryBus`.
- [x] **Mapping discipline** — no ORM attributes touched.
- [x] **No public-contract change** — no routes/events/response fields/DB columns altered. Test class renames are internal (`App\Tests\**`).
- [x] **Coverage preserved** — Functional suite unchanged at 21 scenarios; full suite 58 tests / 128 assertions; every assertion kept verbatim (idempotency double-run + admin-delete 3 assertions).
- [x] **AAA enforced** — `FunctionalTestCase` owns `final #[Test] testExecution()` + abstract `arrange/act/assert`; grep gate confirms it is the only test entry point.
- [x] **Docs synced** — all 9 stale docs/skills updated to present-tense one-class-per-case guidance; stale `--filter` examples replaced; `implemented/` specs left untouched.
- [x] **Gates green** — `make lint-api` (phpstan/cs-fixer/deptrac) clean; `make test-api` green.

## Changelog

| Date | Change |
|------|--------|
| 2026-07-15 | Spec drafted. |
| 2026-07-15 | Pre-implement audit applied: corrected doc diagnosis; added handler-test AAA guidance; added `MeRequiresAuthTest`→`ItRequiresAuthTest`, `SeedAdmin` 1:1 mapping, direct-extender `ItRejectsSelfDeletionTest` and idempotency-double-run callouts; expanded Phase 3 doc list (`scaffold-bounded-context`, `docs/adding-a-bounded-context.md`, `User/AGENTS.md`, stale `--filter`); added coverage-preservation gate. |
| 2026-07-15 | Phases 1+2 implemented (combined — abstract `arrange/act/assert` makes them interdependent; the two direct-extenders must convert in the same commit). Added `prefix="It"` Functional test-suite scoping to suppress abstract-base runner warnings. `make lint-api` clean; `make test-api` green — 58 tests / 128 assertions (21 functional), no coverage lost, grep gate passes. |
| 2026-07-15 | Phase 3 implemented: synced 9 docs/skills (`integration-tests`, `add-command`, `add-query`, `add-route`, `scaffold-bounded-context`, `apps/api/AGENTS.md`, `apps/api/src/User/AGENTS.md`, `.ai/qa/AGENTS.md`, `docs/adding-a-bounded-context.md`) to one-class-per-case + AAA + `Support/{Fixtures,Pages}` + handler-test-via-bus; replaced stale `--filter`/`ControllerTest` references. Final Compliance Report completed. |
