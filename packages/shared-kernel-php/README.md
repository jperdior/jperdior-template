# @jperdior/shared-kernel-php

DDD building blocks shared by every PHP app in this monorepo.

## Exports

| Namespace | What |
|-----------|------|
| `Jperdior\SharedKernel\Domain\Aggregate\AggregateRoot` | Base aggregate with `record()` + `pullDomainEvents()` |
| `Jperdior\SharedKernel\Domain\Bus\Command\{Command,CommandHandler,CommandBus}` | CQRS command interfaces |
| `Jperdior\SharedKernel\Domain\Bus\Query\{Query,QueryHandler,QueryResponse,QueryBus}` | CQRS query interfaces |
| `Jperdior\SharedKernel\Domain\Bus\Event\{DomainEvent,DomainEventSubscriber,EventBus}` | Event interfaces |
| `Jperdior\SharedKernel\Domain\ValueObject\{Uuid,StringValueObject,DateTimeValueObject}` | Value-object base classes |
| `Jperdior\SharedKernel\Domain\Repository\TransactionInterface` | Multi-aggregate transaction contract |
| `Jperdior\SharedKernel\Domain\Clock\ClockInterface` + `Infrastructure\Clock\{SystemClock,FrozenClock}` | Wall clock for time-sensitive logic; FrozenClock for tests |

## Rules

- This package has **zero framework dependencies** beyond `symfony/uid` (for valid UUID generation).
- It MUST NOT import `Doctrine\*`, `Symfony\Component\Messenger\*`, or any infrastructure.
- Apps that consume it implement the Bus interfaces with their preferred transport (the template uses Symfony Messenger).

## Local install (workspace)

Apps reference this package via Composer path repositories:

```json
"repositories": [
  { "type": "path", "url": "../../packages/shared-kernel-php" }
],
"require": {
  "jperdior/shared-kernel-php": "*"
}
```
