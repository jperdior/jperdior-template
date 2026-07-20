# Use-case-centric Application layout + documented cross-context domain events

## TLDR

Flatten each bounded context's `Application/` layer from the `Command/<Action>/` +
`Query/<Action>/` split into a single `Application/<Action>/` folder that holds one
use-case service plus its trigger(s) — a `CommandHandler`, a `QueryHandler`, **or** a
`DomainEventSubscriber` — matching the CodelyTV php-ddd-example convention. On top of that,
document and provide a scaffold for cross-context domain-event communication, so an agent
building (for example) "creating a user creates a tenant" already knows the wiring. No
feature code is added; the template stays generic.

## Overview

The template ships a complete, working event bus: `DomainEvent`,
`AggregateRoot::record()/pullDomainEvents()`, `EventBus`/`MessengerEventBus`,
`DomainEventSubscriber` auto-tagged onto `messenger.bus.event` via `_instanceof` in
`config/services.yaml`, and deptrac enforcing bounded-context isolation. The User context
already emits `UserRegistered`. What is missing is (a) a natural place for a subscriber to
live next to the use case it drives, and (b) any documentation or scaffold for one context
reacting to another's events.

This came from a real gap: on a fresh project the user asked for multi-tenancy where
registering a user provisions a tenant, and the agent had to reverse-engineer the
cross-context event flow because the template never demonstrates it.

## Problem Statement

1. **No home for subscribers.** With use cases grouped under `Application/Command/<Action>/`
   and `Application/Query/<Action>/`, a subscriber that reacts to a domain event and drives
   a use case fits neither bucket. The CQRS split conflates *how a use case is triggered*
   (command / query / event) with *what the use case is*.
2. **Cross-context communication is undocumented.** `docs/adding-a-bounded-context.md` shows
   a subscriber snippet that omits `subscribedTo()`, gives no file placement, no test, and
   no rule for *where a cross-context event class lives* so deptrac stays green. There is no
   `docs/domain-events.md`, no `Application`/AGENTS "Events & Subscribers" section, and no
   scaffolding skill (unlike `/add-command` and `/add-query`).

## Proposed Solution

Adopt the CodelyTV `Application/<Action>/` shape: one folder per use case, containing the
use-case service (`<Action>UseCase`, name retained) and any triggers that drive it. A
command handler, a query handler, and an event subscriber can all coexist in that folder,
each delegating to the same use-case service. Then formalize the cross-context event
convention and document + scaffold it.

Reference: `CodelyTV/php-ddd-example` —
`Mooc/Courses/Application/Create/{CourseCreator, CreateCourseCommand, CreateCourseCommandHandler}`
and `Mooc/CoursesCounter/Application/Increment/{CoursesCounterIncrementer,
IncrementCoursesCounterOnCourseCreated}` (the latter is the event-triggered variant of the
same shape).

## Architecture

### New Application layout (per context)

```
<Context>/Application/<Action>/
├── <Action>UseCase.php            ← the capability (records domain events, persists)
├── <Action>Command.php            ← trigger: write DTO (implements Command)      [optional]
├── <Action>CommandHandler.php     ← trigger: dispatched on command.bus → calls UseCase
├── <Action>Query.php              ← trigger: read DTO (implements Query)         [optional]
├── <Action>QueryHandler.php       ← trigger: dispatched on query.bus → calls UseCase
├── <Response>.php                 ← query response DTO(s)                        [optional]
└── <Verb><Thing>On<Event>.php     ← trigger: DomainEventSubscriber → calls UseCase [optional]
```

The `Command/` and `Query/` grouping folders are removed. Class names are unchanged; only
the namespace segment `\Command\` / `\Query\` is dropped
(`App\User\Application\Command\SignUp` → `App\User\Application\SignUp`).

**Why the move is wiring-safe (verified):**
- deptrac layers collect on `^App\User\.*` (not sub-namespaces) — no rule change.
- DI autowiring in `config/services.yaml` excludes `*Command.php` / `*Query.php` /
  `*Event.php` by filename suffix at **any** depth (`src/**/{Domain,Application}/**/*Command.php`),
  and handlers/subscribers are tagged by **interface** via `_instanceof` — no config change.
- OpenAPI derives from routes/controllers (class names + routes unchanged) — no drift.

### Cross-context event convention

- **Context-internal events** (only the owning context reacts): live in
  `<Context>/Domain/Event/` — where `UserRegistered` stays.
- **Cross-context / integration events** (another context subscribes): live in
  `apps/api/src/Shared/Domain/Event/`. Both the emitting and consuming contexts may depend
  on `Shared` (deptrac allows `User → Shared`), so neither imports the other's `Domain/`.
  This mirrors the convention in the author's own `dungeon-manager` project.
- **Subscriber placement**: in the **consumer** context's `Application/<Action>/` folder,
  next to the use case it drives, named `<Verb><Thing>On<Event>` — implements
  `DomainEventSubscriber`, `subscribedTo()` returns the event class, `__invoke` delegates to
  the use-case service (often by dispatching a local command through the `CommandBus`).

### Bus mechanics (unchanged, documented)

Three synchronous Symfony Messenger buses (`command`, `query`, `event`);
`messenger.bus.event` sets `allow_no_handlers: true` so an event with no subscriber is not
an error. The commented RabbitMQ block in `config/packages/messenger.yaml` is the async
upgrade path; `toPrimitives()`/`fromPrimitives()` on every event make it transport-ready.

## Data Models

None. No entities, value objects, `*Model` classes, or migrations are added or changed.

## API Contracts

None changed. No routes added, removed, or renamed. `openapi.json` and the generated TS
client must be byte-identical after the change (verified via `make gen-api`).

## Phasing

- **Phase 1 — Spec.** This document (committed first on the branch).
- **Phase 2 — Restructure `User/Application`.** `git mv` each `Command/<Action>/` and
  `Query/<Action>/` folder up to `Application/<Action>/`; rewrite `namespace` lines; fix
  every `use` import (12 controllers, 2 console commands, 4 unit tests, intra-Application
  refs). Gate: `make lint-api` + `make test-api`.
- **Phase 3 — `Shared/Domain/Event/` convention.** Create the namespace with a short
  `README.md` documenting its purpose; no event classes moved (UserRegistered stays until a
  real cross-context consumer exists). Gate: `make lint-api`.
- **Phase 4 — `/add-event-subscriber` skill.** New `.ai/skills/add-event-subscriber/SKILL.md`;
  register in `.ai/skills/tiers.json`, `.ai/skills/README.md`, and the AGENTS.md Task Router.
- **Phase 5 — Documentation.** New `docs/domain-events.md`; update `apps/api/AGENTS.md`,
  `docs/adding-a-bounded-context.md`, `docs/ARCHITECTURE.md`, `apps/api/src/User/AGENTS.md`.
- **Phase 6 — Update scaffolding skills.** `add-command`, `add-query`,
  `scaffold-bounded-context` to the flat layout.

Each phase leaves the app green.

## Risks & Impact Review

| # | Scenario | Severity | Mitigation | Residual |
|---|----------|----------|------------|----------|
| 1 | A stale `use` import missed → class-not-found at runtime | Medium | phpstan + functional tests exercise every controller/handler path; grep for old namespaces returns zero | Low |
| 2 | DI autowiring stops finding a moved service | Low | globs are depth-agnostic + interface-tagged; `make test-api` boots the container and dispatches every bus | Low |
| 3 | deptrac flags a new violation after the move | Low | layers key on `^App\User\.*`, unaffected by depth; deptrac runs in `make lint-api` | Low |
| 4 | OpenAPI drift from touched controllers | Low | only `use` lines change; `make gen-api` diff must be empty | Low |
| 5 | Docs describe a `Shared/Domain/Event/` path deptrac would actually reject | Medium | doc read-through validates `User → Shared` is allowed and the example subscriber compiles conceptually | Low |
| 6 | Skill/docs drift from the real flat structure over time | Low | skills + AGENTS.md updated in the same PR; `/sync-context-docs` covers future contexts | Low |

## Integration Coverage

- Existing PHPUnit **Functional** tests under `tests/Functional/User/Application/<Verb>/`
  (dispatch through the real bus) and **Unit** tests under `tests/Unit/User/` must remain
  green after import fixes — they are the regression net proving the move preserved behavior.
- No new runtime behavior ships, so no new PHPUnit/Vitest tests are required. The
  `/add-event-subscriber` skill *documents* the functional-test pattern (SpyEventBus) a
  future subscriber must include.

## Backward Compatibility

Internal source-layout refactor only. Public HTTP/CLI contracts, DB schema, and the TS API
client are unchanged. The `Application/<Action>/` namespaces are internal to `apps/api`;
no published package exposes them.

## Final Compliance Report

- [ ] No `Symfony\*`/`Doctrine\*` imports added to any `Domain/`.
- [ ] No controller calls a handler directly (all via a bus).
- [ ] No cross-context `Domain/`/`Application/` import (deptrac green); cross-context events
      documented as living in `Shared/Domain/Event/`.
- [ ] No Doctrine attributes on domain entities.
- [ ] `openapi.json` + `types.gen.ts` unchanged (`make gen-api` clean).
- [ ] `make lint` + `make test` green.

## Changelog

- 2026-07-21 — Spec authored.
