---
name: add-event-subscriber
description: Add a domain-event subscriber so one bounded context reacts to another context's event (or its own). The producer's event stays in its Domain/Event and is imported directly; scaffolds the consumer's use case, the subscriber, and a functional test. Triggers on "add subscriber", "react to event", "on user registered", "cross-context event", "when X happens do Y in another context".
---

# Add Event Subscriber

Wire a bounded context to **react** to a domain event. This is how contexts communicate
without importing each other: Context **A** emits an event, Context **B** subscribes and
drives one of its own use cases. Read `docs/domain-events.md` for the full model.

## When to use

- "When a user registers, create a tenant" (cross-context reaction).
- "When an order is placed, send a confirmation" (cross-context reaction).
- Any "when X happens, do Y" where X is a state change already recorded as a domain event.

If Y is a brand-new capability, scaffold it with `/add-command` first (or as step 3 below),
then point the subscriber at it. A use case is triggered the same way whether the trigger is
a `CommandHandler`, a `QueryHandler`, or a subscriber.

## Workflow

1. **Locate the event.** Find the event Context A already records (e.g.
   `App\User\Domain\Event\UserRegistered`). If it does not exist, add it to A's aggregate
   first (`$this->record(new ...)`), following the `DomainEvent` shape.

2. **The event stays in Context A's `Domain/Event/`.** No promotion, no moving — a context's
   domain events are its **published contract**, and Context B imports the producer's event
   class directly (`App\A\Domain\Event\<Event>`). deptrac's `DomainEvent` layer allows this
   while still blocking any import of A's aggregates, repositories, value objects, or
   `Application/`. **Requirement:** the event's payload must be **primitive** (strings, arrays,
   scalars — no A value objects), or B transitively depends on A's internals. If A's event
   isn't primitive-only, fix that before subscribing across the boundary.

3. **Ensure the consumer use case exists.** In Context B, the subscriber delegates to a use
   case — it never contains business logic itself. Reuse an existing
   `B/Application/<Action>/<Action>UseCase.php`, or scaffold one with `/add-command`
   (the command + handler + use case triple).

4. **Generate the subscriber** next to that use case:

```
apps/api/src/<ConsumerContext>/Application/<Action>/
├── <Action>UseCase.php               ← the capability (already there)
├── <Action>Command.php               ← command trigger (if any)
├── <Action>CommandHandler.php        ← command trigger (if any)
└── <Verb><Thing>On<Event>.php        ← THIS: the event trigger (DomainEventSubscriber)
```

5. **Add a functional test** under
   `apps/api/tests/Functional/<ConsumerContext>/Application/<Action>/` that publishes the
   source event through the real `EventBus` and asserts B's side effect (repository state, or
   a command observed via `SpyEventBus` / a spy command bus). Name it `It<Scenario>Test`,
   AAA-structured, extending the context's `Base<Action>Test`.

6. **Run `make test-api`** — the container boots, the subscriber is auto-discovered, and the
   event propagates synchronously.

## Subscriber template

```php
<?php

declare(strict_types=1);

namespace App\<ConsumerContext>\Application\<Action>;

use App\<ProducerContext>\Domain\Event\<Event>;              // the producer context's published event
use Jperdior\SharedKernel\Domain\Bus\Command\CommandBus;
use Jperdior\SharedKernel\Domain\Bus\Event\DomainEventSubscriber;

final readonly class <Verb><Thing>On<Event> implements DomainEventSubscriber
{
    public function __construct(private CommandBus $commandBus)
    {
    }

    /** @return array<class-string<\Jperdior\SharedKernel\Domain\Bus\Event\DomainEvent>> */
    public static function subscribedTo(): array
    {
        return [<Event>::class];
    }

    public function __invoke(<Event> $event): void
    {
        // React by driving THIS context's use case. Dispatch a local command so the
        // work runs through the normal command path (validation, events, persistence).
        $this->commandBus->dispatch(new <Action>Command(
            id: $event->aggregateId,
            // …map only the fields this context needs from the event payload
        ));
    }
}
```

For a read-model / projection subscriber (writes a denormalized table via a Doctrine
`Connection` rather than dispatching a command), place it in
`<ConsumerContext>/Infrastructure/Subscriber/` instead, and make the write **idempotent**
(delete-then-insert) so a Messenger retry is safe.

## Rules

- **Subscribers delegate, they don't decide.** No business logic in `__invoke` — dispatch a
  command or call a use case. The rule that catches this: if you'd want a unit test for the
  logic, it belongs in the use case, not the subscriber.
- **Events stay in their owning context's `Domain\Event\`** and are imported directly across
  contexts (deptrac's `DomainEvent` layer). Never import another context's aggregates,
  repositories, value objects, or `Application\` — deptrac fails the build. Keep cross-context
  event payloads primitive. See `docs/domain-events.md`.
- **Name for the reaction:** `<Verb><Thing>On<Event>` — e.g. `CreateTenantOnUserRegistered`,
  `SendConfirmationOnOrderPlaced`. Present tense for the action, the event name after `On`.
- **Auto-tagging:** implementing `DomainEventSubscriber` tags the class onto
  `messenger.bus.event` via `_instanceof` in `config/services.yaml`. Never tag manually.
- **`subscribedTo()` is the source of truth** for which events reach `__invoke`; keep it in
  sync with the typehint.
- **Idempotency:** the event bus is synchronous today but designed to move to a queue
  (`toPrimitives()`/`fromPrimitives()` + the commented RabbitMQ transport). Design the
  reaction to tolerate at-least-once delivery.
- **Map, don't leak:** in `__invoke`, translate the event payload into this context's own
  types/commands. Do not pass the foreign event object deeper than the subscriber.

## Worked example — "creating a user creates a tenant"

1. `App\User\Domain\Event\UserRegistered` — already there; primitive payload; imported directly by Tenant.
2. `App\Tenant\Application\CreateTenant\CreateTenantUseCase` (+ `CreateTenantCommand` /
   `CreateTenantCommandHandler`), scaffolded with `/add-command`.
3. `App\Tenant\Application\CreateTenant\CreateTenantOnUserRegistered` — subscriber that
   dispatches `CreateTenantCommand` built from the event's `aggregateId` (the new owner).

The `CreateTenant` capability is now reachable two ways — the command bus (an admin creating
a tenant directly) and the subscriber (auto-provisioning on registration) — with one use
case behind both.

## Output

```
✅ Subscriber added: <ConsumerContext> reacts to <Event>
   Event: App\<ProducerContext>\Domain\Event\<Event>  (imported directly; DomainEvent layer)
   Subscriber: <ConsumerContext>/Application/<Action>/<Verb><Thing>On<Event>
   Drives: <Action>UseCase (via CommandBus)
   Bus: event.bus (auto-tagged) — synchronous
   Test: tests/Functional/<ConsumerContext>/Application/<Action>/It<Scenario>Test
```
