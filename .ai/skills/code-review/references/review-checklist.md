# Review Checklist (Full)

## Architecture

- [ ] No cross-context `Domain/` or `Application/` imports
- [ ] Controllers dispatch via `CommandBus` / `QueryBus` (never inject a handler)
- [ ] Repository interfaces in `Domain/`, implementations in `Infrastructure/Persistence/`
- [ ] Repository implementations aliased to interfaces in `config/services.yaml`
- [ ] No `#[ORM\*]` on domain entities; ORM attributes belong on `*Model` classes in `Infrastructure/Persistence/Doctrine/`
- [ ] Cross-context interaction via domain events or public application services
- [ ] `tenant_id` columns absent from core entities (`tenancy-php` opt-in only)

## CQRS

- [ ] Commands are imperative names; events are past-tense names
- [ ] Handlers implement `CommandHandler` / `QueryHandler` / `DomainEventSubscriber`
- [ ] No manual messenger tagging — relies on `_instanceof`
- [ ] Async commands are idempotent
- [ ] Queries return DTOs, never entities

## Security

- [ ] Inputs validated at value-object construction
- [ ] Every protected endpoint declares its role/permission
- [ ] No password / token logging
- [ ] Refresh-token rotation present (if auth code touched)
- [ ] No PII / credentials in logs

## Data Integrity

- [ ] Migrations match entity intent (no unrelated churn)
- [ ] Migration generated via `make migrate-diff` or hand-written with rationale
- [ ] Snapshot updated if Doctrine snapshot is in use
- [ ] Workers/subscribers idempotent
- [ ] Single aggregate per write transaction

## Naming & Code

- [ ] Singular aggregate names, plural table names, snake_case columns
- [ ] `declare(strict_types=1);` at top of every PHP file
- [ ] `final readonly` for value objects / DTOs / queries / responses
- [ ] `DateTimeImmutable` everywhere in domain code
- [ ] No `any` in TypeScript
- [ ] No one-letter variables
- [ ] No inline comments narrating WHAT the code does (only non-obvious WHY)

## API / OpenAPI

- [ ] Every controller annotated for Nelmio OpenAPI
- [ ] Request DTOs validated
- [ ] Response DTOs are explicit (no leaking aggregates)
- [ ] HTTP status codes match the action (201 create, 200 read, 204 delete)

## Frontend

- [ ] Forms use shadcn `Form` + zod
- [ ] Server / Client boundary explicit, `"use client"` justified
- [ ] API calls via `@jperdior/api-client-ts`
- [ ] DS tokens used; no hardcoded status colors
- [ ] Dialogs support `Cmd/Ctrl+Enter` + `Escape`
- [ ] Loading / error / empty states present

## Tests

- [ ] PHPUnit Functional for every controller action
- [ ] Vitest + RTL coverage for every non-trivial frontend component, hook, or pure module touched
- [ ] Tests independent (each handles its own auth + cleanup)
- [ ] No `test.only` / `it.only` / debug logs left behind

## Backward Compatibility

- [ ] No event ID renamed/removed
- [ ] No API route renamed/removed
- [ ] No response field removed (additive only)
- [ ] No DB column renamed/removed (additive only)
- [ ] No DI service name renamed
- [ ] No PHP/TS exported type broken
- [ ] Deprecation bridge documented where applicable
