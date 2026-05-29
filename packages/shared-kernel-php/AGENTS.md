# shared-kernel-php — Agents Guidelines

Pure-PHP DDD primitives. **Zero framework dependencies** (except `symfony/uid` for UUID validation/generation). Every PHP app in the monorepo depends on this package.

## Always

- Keep this package framework-agnostic.
- Use `declare(strict_types=1);` at the top of every file.
- Use `final readonly` for value-object base classes; the base is `abstract readonly` and concrete subclasses are `final readonly`.
- Add a PHPUnit test for every public method on the base classes.

## Ask First

- Ask before adding a new interface to `Domain/Bus/*` — these are contracts every app implements.
- Ask before bumping `symfony/uid`.

## Never

- Never import `Doctrine\*`, `Symfony\Component\Messenger\*`, `Symfony\Component\HttpFoundation\*`, or any infrastructure.
- Never add concrete bus implementations here — those live in each app's `Shared/Infrastructure/`.
- Never add a setter to a value object.

## Validation Commands

```bash
cd packages/shared-kernel-php
composer install
vendor/bin/phpstan analyse -c phpstan.dist.neon
vendor/bin/phpunit
```

## Structure

```
src/Domain/
├── Aggregate/AggregateRoot.php
├── Bus/
│   ├── Command/{Command,CommandHandler,CommandBus}.php
│   ├── Query/{Query,QueryHandler,QueryResponse,QueryBus}.php
│   └── Event/{DomainEvent,DomainEventSubscriber,EventBus}.php
├── ValueObject/{Uuid,StringValueObject,DateTimeValueObject}.php
├── Repository/TransactionInterface.php
└── Clock/ClockInterface.php
src/Infrastructure/
└── Clock/{SystemClock,FrozenClock}.php
tests/
└── (PHPUnit tests mirroring src/)
```

## Conventions

- Namespace root: `Jperdior\SharedKernel\` → `src/`.
- Bus marker interfaces are empty (`Command`, `Query`, `CommandHandler`, `QueryHandler`) — auto-tagging in the app's `services.yaml` does the wiring.
- `DomainEvent` is `abstract` (not `interface`) so it can carry the `eventId` and `occurredOn` invariants in the base class.
- `Uuid` is `abstract readonly`; concrete subclasses (e.g. `UserId extends Uuid`) are `final readonly` and inherit `fromString`, `random`, `equals`, `__toString`.
