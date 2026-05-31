# Adding a Bounded Context

A bounded context is a folder under `apps/api/src/<Context>/` with four strict layers. The `User` context is the reference implementation — mirror it exactly for naming and conventions.

---

## Option A — use the skill (recommended)

```
/scaffold-bounded-context
```

The AI agent prompts for the context name and aggregate name, generates the full skeleton, registers Doctrine mapping, wires the repository alias, and opens the migration diff for review.

---

## Option B — manual steps

### 1. Create the folder structure

```
apps/api/src/<Context>/
├── Domain/
│   ├── <Aggregate>.php                         extends AggregateRoot
│   ├── <Aggregate>Id.php                       extends Uuid value object
│   ├── <Aggregate>Repository.php               interface (port)
│   └── Event/
│       └── <Aggregate>Created.php              extends DomainEvent
├── Application/
│   └── Command/
│       └── Create<Aggregate>/
│           ├── Create<Aggregate>Command.php     readonly DTO
│           ├── Create<Aggregate>CommandHandler.php  implements CommandHandler
│           └── Create<Aggregate>UseCase.php    framework-free logic
├── Infrastructure/
│   ├── Persistence/
│   │   ├── Doctrine<Aggregate>Repository.php   extends DoctrineRepository
│   │   └── Doctrine/
│   │       ├── Mapping/
│   │       │   └── <Aggregate>.orm.xml
│   │       └── Type/
│   │           └── <Aggregate>IdType.php       custom DBAL type
│   └── Symfony/
│       └── Resources/config/services.yaml      repository alias
└── Presentation/
    └── Http/
        ├── Create<Aggregate>Controller.php
        └── Dto/
            └── Create<Aggregate>Request.php
```

### 2. Write the domain aggregate

```php
// src/<Context>/Domain/<Aggregate>.php
declare(strict_types=1);

namespace App\<Context>\Domain;

use Jperdior\SharedKernel\Domain\AggregateRoot;
use App\<Context>\Domain\Event\<Aggregate>Created;

final class <Aggregate> extends AggregateRoot
{
    private function __construct(
        private readonly <Aggregate>Id $id,
        // ... value object fields
        private \DateTimeImmutable $createdAt,
    ) {}

    public static function create(<Aggregate>Id $id, ...): self
    {
        $aggregate = new self($id, ...);
        $aggregate->record(new <Aggregate>Created($id->value));
        return $aggregate;
    }

    public function id(): <Aggregate>Id { return $this->id; }
    // ... other accessors
}
```

Key rules:
- Extend `AggregateRoot` (from `shared-kernel-php`). Never implement it yourself.
- `final` class. Use `private` constructor + named static factory.
- All fields are value objects, never raw scalars in the domain.
- Record domain events via `$this->record(...)` — the handler calls `pullDomainEvents()` and publishes them to the event bus.
- `DateTimeImmutable` everywhere. Never `DateTime`.
- No framework imports. No `Symfony\*`, `Doctrine\*`, or anything outside your `Domain/` and `Jperdior\SharedKernel\`.

### 3. Write the custom DBAL type

PHP 8.4's lazy-ghost objects enforce typed property assignment strictly. If a domain entity property is typed `<Aggregate>Id` and Doctrine tries to hydrate a raw UUID string into it, you get a `TypeError`. The fix is a custom DBAL type:

```php
// src/<Context>/Infrastructure/Doctrine/Type/<Aggregate>IdType.php
declare(strict_types=1);

namespace App\<Context>\Infrastructure\Doctrine\Type;

use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Types\Type;
use App\<Context>\Domain\<Aggregate>Id;

final class <Aggregate>IdType extends Type
{
    public const NAME = '<aggregate>_id';

    public function convertToPHPValue(mixed $value, AbstractPlatform $platform): ?<Aggregate>Id
    {
        return null !== $value ? <Aggregate>Id::fromString((string) $value) : null;
    }

    public function convertToDatabaseValue(mixed $value, AbstractPlatform $platform): ?string
    {
        return $value instanceof <Aggregate>Id ? $value->value : null;
    }

    public function getSQLDeclaration(array $column, AbstractPlatform $platform): string
    {
        return $platform->getStringTypeDeclarationSQL($column);
    }

    public function getName(): string { return self::NAME; }
}
```

Register it in `apps/api/config/packages/doctrine.yaml`:

```yaml
doctrine:
    dbal:
        types:
            <aggregate>_id: App\<Context>\Infrastructure\Doctrine\Type\<Aggregate>IdType
```

### 4. Write the XML mapping

No `#[ORM\*]` attributes on domain entities. Doctrine mapping lives in XML:

```xml
<!-- src/<Context>/Infrastructure/Persistence/Doctrine/Mapping/<Aggregate>.orm.xml -->
<?xml version="1.0" encoding="UTF-8"?>
<doctrine-mapping xmlns="http://doctrine-project.org/schemas/orm/doctrine-mapping"
                  xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance"
                  xsi:schemaLocation="http://doctrine-project.org/schemas/orm/doctrine-mapping
                  https://www.doctrine-project.org/schemas/orm/doctrine-mapping.xsd">

    <entity name="App\<Context>\Domain\<Aggregate>" table="<aggregates>">
        <id name="id" type="<aggregate>_id" column="id"/>
        <field name="createdAt" type="datetime_immutable" column="created_at"/>
        <!-- other fields -->
    </entity>
</doctrine-mapping>
```

Table names: plural, snake_case. Column names: snake_case. ID type: your custom DBAL type. `DateTimeImmutable` fields use `datetime_immutable`.

### 5. Register the Doctrine mapping

In `apps/api/config/packages/doctrine.yaml`, under `orm.mappings`:

```yaml
doctrine:
    orm:
        auto_mapping: false
        mappings:
            <Context>:
                type: xml
                is_bundle: false
                dir: '%kernel.project_dir%/src/<Context>/Infrastructure/Persistence/Doctrine/Mapping'
                prefix: 'App\<Context>\Domain'
                alias: <Context>
```

### 6. Wire the repository alias

Each context owns its own DI wiring. Create:

```yaml
# src/<Context>/Infrastructure/Symfony/Resources/config/services.yaml
services:
    App\<Context>\Domain\<Aggregate>Repository:
        alias: App\<Context>\Infrastructure\Persistence\Doctrine<Aggregate>Repository
```

Then import it from the global `config/services.yaml`:

```yaml
imports:
    - { resource: '../src/Shared/Infrastructure/Symfony/Resources/config/services.yaml' }
    - { resource: '../src/User/Infrastructure/Symfony/Resources/config/services.yaml' }
    - { resource: '../src/<Context>/Infrastructure/Symfony/Resources/config/services.yaml' }
```

This mirrors the pattern already established by the `User` context. The alias is context-owned — the global `services.yaml` imports it but doesn't define it.

### 7. Generate and review the migration

```bash
make migrate-diff   # generates apps/api/migrations/Version<timestamp>.php
```

Open the generated file. Review the SQL. Verify it matches what you expect. Then:

```bash
make migrate
```

Never run migrations without reviewing the SQL first. The `migrate-diff` command diffs the current schema against your XML mappings — it will catch typos in column names and type mismatches.

### 8. Write the command handler and use case

The handler is Symfony Messenger glue. The use case is pure PHP:

```php
// Application/Command/Create<Aggregate>/<Verb>CommandHandler.php
final class Create<Aggregate>CommandHandler implements CommandHandler
{
    public function __construct(
        private readonly Create<Aggregate>UseCase $useCase,
    ) {}

    public function __invoke(Create<Aggregate>Command $command): void
    {
        $this->useCase->execute($command);
        // drain and publish domain events here if needed
    }
}

// Application/Command/Create<Aggregate>/<Verb>UseCase.php
final class Create<Aggregate>UseCase
{
    public function __construct(
        private readonly <Aggregate>Repository $repository,
    ) {}

    public function execute(Create<Aggregate>Command $command): void
    {
        $aggregate = <Aggregate>::create(
            <Aggregate>Id::fromString($command->id),
            // ...
        );
        $this->repository->save($aggregate);
    }
}
```

The handler is tagged automatically via the `_instanceof` block in `config/services.yaml` — no YAML changes needed. The use case has no framework imports and can be unit-tested without booting Symfony.

### 9. Write functional tests

```
tests/Functional/<Context>/Presentation/Http/Create<Aggregate>ControllerTest.php
```

Use `FunctionalTestCase` as the base. Tests run in transactional rollback — no data bleeds between cases. Mirror the structure in `tests/Functional/User/`.

---

## Cross-context communication

**Never** import another context's `Domain/` or `Application/` namespaces. `deptrac` enforces this in CI — a cross-context import fails the build.

### Cross-context ID references

If your context stores a reference to an entity that lives in another context (e.g. the user who owns a resource), define a **local value object** with a name that fits *this* context's ubiquitous language:

```php
// Order\Domain\ValueObject\OwnerId.php
final readonly class OwnerId extends UuidValueObject {}
```

Do **not** import `User\Domain\ValueObject\UserId`. The two value objects hold the same UUID — they are different concepts in different languages. `OwnerId` belongs to Order; Order does not need to know about the User context to be valid.

### Event-based communication

Communication between contexts is via domain events on the event bus:

```php
// Context A: publish
$this->record(new OrderPlaced($orderId->value, $userId->value));

// Context B: subscribe
final class SendOrderConfirmation implements DomainEventSubscriber
{
    public function __invoke(OrderPlaced $event): void { ... }
}
```

If Context B needs a read projection of Context A's data, subscribe to its events and maintain a local read model. Never reach into A's repository directly.

---

## Single-tenant by design

This template has no `tenant_id` columns. All contexts are single-tenant. If your project requires multi-tenancy, fork the template and add your own implementation — a Doctrine `SQLFilter` + request-scoped `TenantContext` is the standard approach, but the details vary enough per project that the template deliberately stays out of that decision.
