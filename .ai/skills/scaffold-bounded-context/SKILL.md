---
name: scaffold-bounded-context
description: Scaffold a new bounded context under apps/api/src/<Context>/ with Domain, Application, Infrastructure, Presentation layers. Mirrors the User/Note layout. Triggers on "new bounded context", "scaffold context", "add a context".
---

# Scaffold Bounded Context

Generate the four-layer DDD skeleton for a new bounded context.

## Workflow

1. **Ask for the context name** (PascalCase singular, e.g. `Billing`, `Subscription`, `Workspace`). If unclear, propose options.
2. **Identify the aggregate root**: which entity is the primary aggregate? Most contexts have exactly one.
3. **Identify value objects** that protect invariants (e.g. `BillingPlanId`, `MonetaryAmount`).
4. **Generate the skeleton**:

```
apps/api/src/<Context>/
├── Domain/
│   ├── <Aggregate>.php                       ← extends Shared\Domain\Aggregate\AggregateRoot
│   ├── <Aggregate>Id.php                     ← Uuid-backed value object
│   ├── <Aggregate>Repository.php             ← interface
│   ├── Event/<Aggregate>Created.php          ← initial domain event
│   └── Exception/<Aggregate>NotFound.php
├── Application/
│   ├── Command/{Create,Update,Delete}<Aggregate>/
│   │   ├── <Verb><Aggregate>Command.php      ← final readonly
│   │   └── <Verb><Aggregate>CommandHandler.php
│   └── Query/{Get,List}<Aggregate>/
│       ├── <Verb><Aggregate>Query.php
│       ├── <Verb><Aggregate>QueryHandler.php
│       └── <Verb><Aggregate>Response.php
├── Infrastructure/
│   └── Persistence/
│       ├── Doctrine<Aggregate>Repository.php ← extends Shared\Infrastructure\Doctrine\DoctrineRepository
│       └── Doctrine/Mapping/<Aggregate>.orm.xml
└── Presentation/
    └── Http/
        ├── {Create,Update,Delete,Get,List}<Aggregate>Controller.php
        └── Dto/{Create,Update}<Aggregate>Request.php
```

5. **Update `config/services.yaml`**: alias the repository interface to the Doctrine implementation:
   ```yaml
   App\<Context>\Domain\<Aggregate>Repository: '@App\<Context>\Infrastructure\Persistence\Doctrine<Aggregate>Repository'
   ```
6. **Update `config/packages/doctrine.yaml`**: register the context's mapping namespace.
7. **Generate the first migration**: `make migrate-diff`. Review the SQL.
8. **Generate the AGENTS.md** at `apps/api/src/<Context>/AGENTS.md` using `/create-agents-md`.
9. **Generate the first test**: `apps/api/tests/Functional/<Context>/<Aggregate>Test.php` (smoke test for the Create endpoint).

## Rules

- **One aggregate root per context** by default. Multiple aggregates require justification.
- **No cross-context imports** in the skeleton. If the context needs data from `User`, design the access via domain events or a public application service.
- **No Doctrine attributes** on the domain entity. XML only.
- **Value objects validated in their constructor** (`InvalidArgumentException` on bad input).
- **Repository interface in `Domain/`**, never in `Infrastructure/`.
- **Initial migration is bounded** to the new tables only — no churn on existing tables.

## Output

```
✅ Bounded context scaffolded: <Context>
   Files created: ~14
   Aggregate: <Aggregate>
   First migration: <timestamp> Version<…>.php
   AGENTS.md: apps/api/src/<Context>/AGENTS.md
   Next: /add-command, /add-query, /add-route, or apply the migration with `make migrate`
```
