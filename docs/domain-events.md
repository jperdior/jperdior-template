# Domain Events & Cross-Context Communication

Bounded contexts never import each other's internals. They communicate through two
boundary-clean channels, both mediated by a bus:

1. **Domain events** (`event.bus`) — one context emits, another *reacts* asynchronously. This is
   how "when a user registers, create a tenant" is built: the `User` context emits an event; the
   `Tenant` context subscribes and drives its own use case. This document covers this channel.
2. **Bus-dispatched commands & queries** (`command.bus` / `query.bus`) — one context *acts on* or
   *reads from* another synchronously, in-request, by dispatching the other context's `*Command` or
   `*Query`. See [Cross-context CQRS](#cross-context-cqrs-commands--queries) at the end.

A context's **published contract** — the part other contexts may import — is its `Domain/Event/`
classes **plus** its `*Command` / `*Query` / Response DTOs. Its aggregates, repositories, value
objects, and executable Application classes (`*Handler` / `*UseCase` / `*Subscriber`) stay private,
and `deptrac` fails the build on any cross-context import of those.

The infrastructure is already wired. This doc explains the flow and the conventions; use
`/add-event-subscriber` to scaffold a consumer.

## The flow in one loop

```
Aggregate.record(event)                 (Domain — an aggregate mutates and records what happened)
   → useCase.pullDomainEvents()         (Application — the use case drains recorded events)
   → EventBus.publish(...events)        (Application — publishes onto messenger.bus.event)
   → Symfony Messenger (synchronous)
   → DomainEventSubscriber.__invoke()   (Application of ANOTHER context — reacts)
   → useCase(...)                        (invokes the consumer's own use case directly)
```

Every arrow already exists in the template for `User`'s `UserRegistered`; only the
subscriber side is left for you to add when a second context needs to react.

## The building blocks (all shipped)

| Piece | Where | Role |
|-------|-------|------|
| `DomainEvent` | `Jperdior\SharedKernel\Domain\Bus\Event\DomainEvent` | Base class: `aggregateId`, `eventId`, `occurredOn`, + `eventName()` / `toPrimitives()` / `fromPrimitives()` |
| `AggregateRoot` | `Jperdior\SharedKernel\Domain\Aggregate\AggregateRoot` | `record()` (protected) + `pullDomainEvents()` |
| `EventBus` | `Jperdior\SharedKernel\Domain\Bus\Event\EventBus` | `publish(DomainEvent ...$events)` |
| `MessengerEventBus` | `App\Shared\Infrastructure\Bus\MessengerEventBus` | Dispatches each event onto `messenger.bus.event` |
| `DomainEventSubscriber` | `Jperdior\SharedKernel\Domain\Bus\Event\DomainEventSubscriber` | `subscribedTo(): array` — the events that reach `__invoke` |

Subscribers are auto-wired: the `_instanceof` block in `apps/api/config/services.yaml` tags
every `DomainEventSubscriber` onto `messenger.bus.event`. No manual tagging, no config edit —
implementing the interface is enough.

## 1 — Define the event

An event is a `final` class extending `DomainEvent`. `eventName()` follows
`<context>.<aggregate>.<action_past_tense>`. `toPrimitives()`/`fromPrimitives()` make it
JSON-serializable so it is transport-ready if the bus ever moves to a queue.

```php
// App\User\Domain\Event\UserRegistered  (context-internal — see "Where events live")
final class UserRegistered extends DomainEvent
{
    public function __construct(
        string $aggregateId,
        public readonly string $email,
        public readonly array $roles,
        public readonly string $registeredAt,
        ?string $eventId = null,
        ?string $occurredOn = null,
    ) {
        parent::__construct($aggregateId, $eventId, $occurredOn);
    }

    public static function eventName(): string
    {
        return 'user.account.registered';
    }

    // toPrimitives() / fromPrimitives() — payload ⇄ object, see the real file
}
```

## 2 — Record it in the aggregate

The aggregate appends the event to an in-memory list. It never touches the bus.

```php
// App\User\Domain\User
public static function register(/* … */): self
{
    $user = new self(/* … */);
    $user->record(new UserRegistered(
        $id->value(),
        $email->value,
        array_map(static fn (Role $r) => $r->value, $roles),
        $createdAt->format(\DateTimeInterface::ATOM),
    ));

    return $user;
}
```

## 3 — Publish from the use case

The use case persists the aggregate, then drains and publishes its events. The command
handler stays a thin adapter that just calls the use case.

```php
// App\User\Application\SignUp\SignUpUseCase
public function __invoke(SignUpCommand $command): void
{
    $user = User::register(/* … */);

    $this->users->save($user);
    $this->eventBus->publish(...$user->pullDomainEvents());   // ← onto messenger.bus.event
}

// App\User\Application\SignUp\SignUpCommandHandler — the trigger
final readonly class SignUpCommandHandler implements CommandHandler
{
    public function __construct(private SignUpUseCase $useCase) {}

    public function __invoke(SignUpCommand $command): void
    {
        ($this->useCase)($command);
    }
}
```

## 4 — Subscribe from another context

A subscriber lives in the **consumer** context's `Application/<Action>/` folder, beside the
use case it drives. It implements `DomainEventSubscriber`, declares the events in
`subscribedTo()`, and in `__invoke` **invokes the use case directly** — the same use case the
`CommandHandler` invokes. It contains no business logic itself: it maps the event's primitive
payload to the use case's input (generating any ids, as the composition root) and calls it.
It does **not** dispatch a command through the command bus to reach its own use case. This is
the CodelyTV php-ddd-example shape (e.g.
`Mooc/CoursesCounter/Application/Increment/IncrementCoursesCounterOnCourseCreated`).

```php
// App\Tenant\Application\CreateTenant\CreateTenantOnUserRegistered
final readonly class CreateTenantOnUserRegistered implements DomainEventSubscriber
{
    public function __construct(private CreateTenantUseCase $useCase) {}

    public static function subscribedTo(): array
    {
        return [UserRegistered::class];        // App\User\Domain\Event\UserRegistered — imported directly
    }

    public function __invoke(UserRegistered $event): void
    {
        ($this->useCase)(new CreateTenantCommand(
            tenantId: TenantId::random()->value,   // ids generated here (subscriber = composition root)
            ownerUserId: $event->aggregateId,      // the newly-registered user
        ));
    }
}
```

The `CreateTenant` use case is reachable **two ways** with one implementation behind both:
the command bus (`CreateTenantCommand` → `CreateTenantCommandHandler` → `CreateTenantUseCase`,
an admin creating a tenant) and this subscriber (`CreateTenantOnUserRegistered` →
`CreateTenantUseCase`, auto-provisioning on registration). **Both adapters invoke the same use
case** — the subscriber does not go through the command handler. That is the whole point of the
`Application/<Action>/` layout: a use case is independent of what triggers it.

## Where events live — the deptrac rule

Every event lives in **its owning context's `Domain/Event/`** — always, whether one context
reacts or several. `UserRegistered` stays `App\User\Domain\Event\UserRegistered`; a consumer
in another context imports **that class directly**.

```
apps/api/src/User/Domain/Event/UserRegistered.php     → App\User\Domain\Event\UserRegistered
apps/api/src/Order/Domain/Event/OrderPlaced.php       → App\Order\Domain\Event\OrderPlaced
```

This is allowed because **a context's domain events are its published contract.** `deptrac`
has a dedicated `DomainEvent` layer (`apps/api/deptrac.yaml`) that collects every
`App\<Context>\Domain\Event\*` class; every context may depend on that layer. So
`Tenant` may import `App\User\Domain\Event\UserRegistered`, but importing
`App\User\Domain\User` (the aggregate), a repository, or a value object still **fails the
build** — the context layers exclude the `Domain\Event\` namespace, so only events are
cross-importable. Adding a new context means adding its layer with the same exclusion and
`DomainEvent` in its ruleset (the `/scaffold-bounded-context` checklist covers this).

> **The one rule that makes this safe:** a cross-context event's payload must be
> **primitive** — strings, arrays, scalars (see `UserRegistered`). Never type a public
> property as one of the producer's value objects, or importing the event would transitively
> drag in the producer's internals and defeat the boundary. `toPrimitives()` /
> `fromPrimitives()` already push you toward this.

## The buses

Three synchronous Symfony Messenger buses, configured in
`apps/api/config/packages/messenger.yaml`:

| Bus | Carries | Handler interface |
|-----|---------|-------------------|
| `messenger.bus.command` | commands | `CommandHandler` |
| `messenger.bus.query` | queries | `QueryHandler` |
| `messenger.bus.event` | domain events | `DomainEventSubscriber` |

`messenger.bus.event` sets `allow_no_handlers: true` — publishing an event with **no**
subscriber is intentional and must not error. All three run **synchronously** by default (no
worker): a subscriber runs in-process, inside the same request, before the response returns.

### Going async (optional)

The commented block in `messenger.yaml` is the RabbitMQ upgrade path: set
`MESSENGER_TRANSPORT_DSN`, run `make start-async`, and route specific messages to the `async`
transport. Because every event implements `toPrimitives()`/`fromPrimitives()`, no event code
changes. Design every subscriber to tolerate **at-least-once** delivery (idempotent
reactions) so the switch is safe.

## Testing a subscriber

Publish the source event through the real `EventBus` and assert the consumer's side effect —
the use case ran, so assert the resulting repository state (e.g. the new tenant + membership).
Use the test doubles in `apps/api/tests/Doubles/` — e.g. `SpyEventBus` to capture published
events, in-memory repositories to inspect what the use case wrote.
Functional tests live under `tests/Functional/<ConsumerContext>/Application/<Action>/` as
`It<Scenario>Test` (AAA, one scenario per class); see `.ai/qa/AGENTS.md`.

## Rules of thumb

- **Aggregates record, use cases publish, subscribers delegate.** A subscriber invokes its use
  case directly (never via the command bus); no business logic in a subscriber — if it deserves a
  unit test, it belongs in a use case.
- **Events (and CQRS messages) are a context's published contract** — a consumer imports the
  producer's `Domain\Event\<Event>`, or its `*Command` / `*Query` / Response DTO, directly (deptrac's
  `DomainEvent` + `PublicMessage` layers allow it). Never import another context's aggregates,
  repositories, value objects, or executable Application classes (`*Handler`/`*UseCase`/`*Subscriber`)
  — those stay private. Keep cross-context payloads **primitive** so the import stays clean.
- **Name the reaction `<Verb><Thing>On<Event>`** — `CreateTenantOnUserRegistered`.
- **Events are past tense** (`UserRegistered`); commands are imperative (`CreateTenant`).
- **Design for retries** — the bus is sync now but built to move to a queue.

## Cross-context CQRS (commands & queries)

When a context needs a **synchronous** result from another — read a value, or trigger a state
change and know it happened *now* — dispatch the other context's command/query through the bus
instead of importing its internals. Domain events are for *reactions* (fire-and-forget); CQRS
messages are for *in-request* acts and reads.

```php
// App\Order\Application\PlaceOrder\PlaceOrderUseCase (Order context)
use App\Customer\Application\GetCustomer\GetCustomerQuery;      // Customer's PublicMessage — importable
use Jperdior\SharedKernel\Domain\Bus\Query\QueryBus;

final class PlaceOrderUseCase
{
    public function __construct(private QueryBus $queryBus, /* … */) {}

    public function __invoke(PlaceOrderCommand $command): void
    {
        // Reads Customer's state through the bus. If the customer doesn't exist, Customer's handler
        // throws CustomerNotFound → 404 (mapped by Customer's status map). Order imports the QUERY +
        // its RESPONSE, never Customer's handler/use-case/aggregate.
        $this->queryBus->ask(new GetCustomerQuery($command->customerId));
        // … proceed to place the order …
    }
}
```

What crosses the boundary here: `GetCustomerQuery` and `CustomerResponse` — both `PublicMessage`
classes carrying primitives. What does **not**: `GetCustomerQueryHandler`, `GetCustomerUseCase`, the
`Customer` aggregate, `CustomerRepository`. deptrac's `PublicMessage` layer
(`^App\<Ctx>\Application\*` minus the classes implementing a bus handler/subscriber interface, minus
`*UseCase`) permits the former and still
fails the build on the latter.

Rules:
- **Messages carry primitives + shared identifier VOs only** — never a producer's domain VO.
- **Dispatch, never import the handler.** The message class is the contract; the handler resolves on
  the producer's side via the bus.
- **A cross-context read is still a read** — put the dispatch in the consumer's use case (so it holds
  for every entry point, HTTP or not — L-008), not only in a controller.

## See also

- `.ai/skills/add-event-subscriber/SKILL.md` — scaffold a consumer.
- `docs/adding-a-bounded-context.md` — cross-context communication in a new context.
- `docs/ARCHITECTURE.md` — the three buses and layer responsibilities.
- `apps/api/AGENTS.md` — Events & Subscribers rules.
