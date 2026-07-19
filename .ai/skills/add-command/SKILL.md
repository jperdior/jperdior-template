---
name: add-command
description: Add a CQRS write command + handler + functional test to an existing bounded context. Triggers on "add command", "new command", "scaffold command".
---

# Add Command

Add a Symfony Messenger command and handler to an existing bounded context.

## Workflow

1. **Identify the context and aggregate**. The command must live inside an existing bounded context.
2. **Name the command** in imperative form: `CreateNote`, `UpdateNote`, `ArchiveSubscription`. Suffix: `Command`.
3. **Generate the files**:

```
apps/api/src/<Context>/Application/Command/<Verb>/
├── <Verb>Command.php           ← final readonly, implements Shared\Domain\Bus\Command\Command
└── <Verb>CommandHandler.php    ← implements Shared\Domain\Bus\Command\CommandHandler
```

4. **Wire dependencies**: inject domain repositories + clock + transaction interface from `shared-kernel-php`. NEVER inject Doctrine directly.
5. **Validate inputs at value-object construction** inside the command class constructor.
6. **Emit a domain event** if the command changes state. The aggregate records it via `$this->record(new ...)`; the handler drains via `pullDomainEvents()` and dispatches to the event bus.
7. **Add a functional test** — one class per scenario, named `It<Scenario>Test`, under `apps/api/tests/Functional/<Context>/Application/<Verb>/`, extending an abstract `Base<Verb>Test`. It's AAA (`arrange/act/assert`, enforced by `FunctionalTestCase`): `arrange()` builds fixtures, `act()` dispatches the command through the `CommandBus` (no page object — handler tests exercise the bus, not HTTP), `assert()` checks repository state / a `SpyEventBus`. Only `It*Test` classes are collected.
8. **Run `make test-api`** to confirm.

## Command Template

```php
<?php

declare(strict_types=1);

namespace App\<Context>\Application\Command\<Verb>;

use App\Shared\Domain\Bus\Command\Command;

final readonly class <Verb>Command implements Command
{
    public function __construct(
        public string $id,
        public string $title,
        // ...
    ) {
    }
}
```

## Handler Template

```php
<?php

declare(strict_types=1);

namespace App\<Context>\Application\Command\<Verb>;

use App\<Context>\Domain\<Aggregate>;
use App\<Context>\Domain\<Aggregate>Id;
use App\<Context>\Domain\<Aggregate>Repository;
use App\Shared\Domain\Bus\Command\CommandHandler;
use App\Shared\Domain\Bus\Event\EventBus;

final readonly class <Verb>CommandHandler implements CommandHandler
{
    public function __construct(
        private <Aggregate>Repository $repository,
        private EventBus $eventBus,
    ) {
    }

    public function __invoke(<Verb>Command $command): void
    {
        $aggregate = <Aggregate>::create(
            <Aggregate>Id::fromString($command->id),
            // …value-object construction validates inputs
        );

        $this->repository->save($aggregate);
        $this->eventBus->publish(...$aggregate->pullDomainEvents());
    }
}
```

## Rules

- **Commands are imperative**. Past-tense names are for events, not commands.
- **`final readonly`** on the command class.
- **No Doctrine in handlers** — only domain interfaces.
- **One aggregate per transaction**. If your command needs to touch two aggregates, split into two commands and chain via events.
- **Cross-context ID references**: if the command carries the ID of an entity from another bounded context (e.g. `userId`), define a local value object for it in `<Context>\Domain\ValueObject\` with a context-appropriate name (e.g. `OwnerId`, not `UserId`). Extend `UuidValueObject` from the shared kernel. Never import the other context's ID type — that couples the domain layers.
- **Idempotency**: design for retries. The same command applied twice MUST produce the same outcome (or fail cleanly).
- **Auto-tagging**: `_instanceof: App\Shared\Domain\Bus\Command\CommandHandler` in `config/services.yaml` wires this to the `command.bus` Messenger transport. Never tag manually.

## Output

```
✅ Command added: <Context>/<Verb>
   Files: 2 (+ 1 test)
   Bus: command.bus
   Wiring: auto-tagged via _instanceof
   Next: /add-route to expose it via HTTP
```
