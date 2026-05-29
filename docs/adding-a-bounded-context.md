# Adding a Bounded Context

A bounded context in this template = one folder under `apps/api/src/<Context>/` with four layers. The `Note` context is the reference implementation.

## Option A — use the skill (recommended)

```
/scaffold-bounded-context
```

The skill prompts for the context name and aggregate name, then generates the full skeleton.

## Option B — manual steps

### 1. Create the folder structure

```
apps/api/src/<Context>/
├── Domain/
│   ├── <Aggregate>.php            # extends AggregateRoot
│   ├── <Aggregate>Id.php          # extends Uuid VO
│   ├── <Aggregate>Repository.php  # interface (port)
│   └── Event/
│       └── <Aggregate>Created.php # extends DomainEvent
├── Application/
│   └── Command/
│       └── Create<Aggregate>/
│           ├── Create<Aggregate>Command.php
│           └── Create<Aggregate>CommandHandler.php  # implements CommandHandler
├── Infrastructure/
│   └── Persistence/
│       ├── Doctrine<Aggregate>Repository.php        # extends DoctrineRepository
│       └── Doctrine/Mapping/
│           └── <Aggregate>.orm.xml
└── Presentation/
    └── Http/
        └── Create<Aggregate>Controller.php          # invokable, #[Route(...)]
```

Mirror `apps/api/src/Note/` exactly for naming and import conventions.

### 2. Register the Doctrine mapping

In `apps/api/config/packages/doctrine.yaml`, under `orm.mappings`:

```yaml
<Context>:
    type: xml
    is_bundle: false
    dir: '%kernel.project_dir%/src/<Context>/Infrastructure/Persistence/Doctrine/Mapping'
    prefix: 'App\<Context>\Domain'
    alias: <Context>
```

### 3. Register the repository alias

In `apps/api/config/services.yaml`:

```yaml
App\<Context>\Domain\<Aggregate>Repository:
    alias: App\<Context>\Infrastructure\Persistence\Doctrine<Aggregate>Repository
```

### 4. Generate and review the migration

```bash
make migrate-diff   # reviews diff against current schema
# Review the generated file under apps/api/migrations/
make migrate        # apply
```

### 5. Write functional tests

```
apps/api/tests/Functional/<Context>/Presentation/Http/Create<Aggregate>ControllerTest.php
```

Use `apps/api/tests/Functional/Note/` as the reference.

### 6. Update the Task Router

If the new context introduces a new task pattern (e.g. webhooks, scheduled jobs), add a row to the Task Router table in `AGENTS.md`.

## Rules

- **Never** import `App\<OtherContext>\Domain\` or `App\<OtherContext>\Application\`. `deptrac` enforces this in CI.
- Cross-context communication = publish a domain event via `EventBus`, subscribe in the other context with `DomainEventSubscriber`.
- No `tenant_id` column by default. See `docs/multitenancy.md` to opt in.
