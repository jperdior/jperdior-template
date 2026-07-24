# apps/api — Agents Guidelines

Symfony 7.4 modular monolith. One app, many bounded contexts under `src/<Context>/`. Single image, two runtime processes: `api` (FrankenPHP, serving HTTP directly) and `worker` (`messenger:consume async`).

## Always

- Match the four-layer layout in every context: `Domain/`, `Application/`, `Infrastructure/`, `Presentation/`.
- Group the Application layer by **use case**, not by trigger: `Application/<Action>/` holds the `<Action>UseCase` plus its trigger(s) — a `CommandHandler`, a `QueryHandler`, and/or a `DomainEventSubscriber`, all delegating to that one use case. There is no `Command/` or `Query/` grouping folder.
- Dispatch through `CommandBus` / `QueryBus` from controllers. Never inject a handler.
- Place repository interfaces in `Domain/`, Doctrine implementations in `Infrastructure/Persistence/`, alias them in `config/services.yaml`.
- Use PHP attributes on `*Model` persistence classes under `Infrastructure/Persistence/Doctrine/`. Register each context with `type: attribute` in `config/packages/doctrine.yaml`. Never put `#[ORM\*]` on Domain entities.
- `declare(strict_types=1);` at the top of every file.
- `final readonly` for value objects, DTOs, commands, queries, responses.
- `DateTimeImmutable` everywhere in domain code; never `DateTime`.
- Validate inputs at value-object construction (`UserId::fromString()`).
- Emit domain events via `$this->record(...)`; drain via `pullDomainEvents()` in the handler; publish via the event bus.
- Annotate every controller with Nelmio OpenAPI attributes.

## Ask First

- Ask before adding a new bundle.
- Ask before bumping Symfony major/minor versions.
- Ask before adding a non-`doctrine://` Messenger transport.

## Never

- **Never** import another context's aggregates, repositories, value objects, or its **executable** Application classes (`*Handler`, `*UseCase`, `*Subscriber`). CI `deptrac` enforces this. A context's **published contract** is cross-importable: its `Domain/Event/` classes **and** its `*Command` / `*Query` / Response DTOs (the `PublicMessage` layer). A cross-context command/query is only ever **dispatched through the bus** — you import the message class, never the handler. (See Cross-Context Communication and Events & Subscribers below.)
- **Never** add `#[ORM\*]` attributes to domain entities. ORM mapping belongs on `*Model` classes in `Infrastructure/Persistence/Doctrine/`.
- **Never** call `em->find()` from a controller. Use a query.
- **Never** catch a domain exception in a controller. Context-specific HTTP statuses live in the context's `ExceptionStatusMapProvider` (`Presentation/Http/<Context>ExceptionStatusMap.php`); everything else falls back to the Shared `ExceptionListener`'s generic mapping (`DomainException`→409, `InvalidArgumentException`→400).
- **Never** log credentials, tokens, or password hashes.

## Validation Commands

```bash
# Inside docker:
make api-shell
composer install
vendor/bin/phpstan analyse -c phpstan.dist.neon --memory-limit=512M
vendor/bin/php-cs-fixer fix --dry-run --diff
vendor/bin/deptrac analyse --no-progress
vendor/bin/phpunit

# From repo root:
make lint-api
make test-api
make migrate
make migrate-diff
```

## Structure

```
apps/api/
├── bin/console
├── bin/start                          ← dev container startup script
├── config/
│   ├── bundles.php
│   ├── routes.yaml
│   ├── services.yaml             ← _instanceof tags for 3 buses + service aliases
│   └── packages/
│       ├── doctrine.yaml          ← attribute mapping per context
│       ├── doctrine_migrations.yaml
│       ├── framework.yaml
│       ├── messenger.yaml         ← command.bus / query.bus / event.bus
│       ├── lexik_jwt_authentication.yaml
│       ├── gesdinet_jwt_refresh_token.yaml
│       ├── nelmio_api_doc.yaml
│       ├── nelmio_cors.yaml
│       ├── security.yaml
│       ├── twig.yaml
│       └── validator.yaml
├── migrations/                    ← Doctrine migrations, one per logical change
├── public/index.php
├── src/
│   ├── Kernel.php
│   ├── Shared/                    ← cross-context Symfony adapters
│   │   ├── Infrastructure/
│   │   │   ├── Bus/{MessengerCommandBus,MessengerQueryBus,MessengerEventBus}.php
│   │   │   ├── Doctrine/{DoctrineRepository,DoctrineTransaction}.php
│   │   │   └── Symfony/Resources/config/services.yaml
│   │   └── Presentation/Http/{ExceptionListener,ExceptionStatusMapProvider}.php
│   ├── User/                      ← bounded context: auth
│   └── <NextContext>/             ← drop a folder, get a context
└── tests/
    ├── Unit/                          ← aggregate/VO/use-case tests; fast, no DB
    ├── Doubles/                       ← in-memory/fake adapters for the domain ports (test-only; never wired into prod DI)
    ├── Functional/                    ← full-stack tests against Postgres; one class per scenario (It*Test), AAA-enforced
    ├── Support/{Fixtures,Pages}/      ← data fixtures + HTTP page objects (App\Tests\Support\*)
    └── bootstrap.php
```

Functional tests are **one class per scenario**. `FunctionalTestCase` (extends `WebTestCase`)
owns `final #[Test] testExecution()` → `arrange()/act()/assert()` (all abstract) and wraps
each test in a rolled-back DB transaction. Shared setup + a default `arrange()` live in an
abstract `Base<UseCase>Test`; each scenario is a `final It<Scenario>Test`. The Functional
suite is scoped `prefix="It"`, so only `It*Test` classes are collected. See `.ai/qa/AGENTS.md`
and `/integration-tests`.

## CQRS Wiring

`config/services.yaml` has the `_instanceof` block that tags every handler:

```yaml
_instanceof:
    Jperdior\SharedKernel\Domain\Bus\Command\CommandHandler:
        tags: [ { name: messenger.message_handler, bus: messenger.bus.command } ]
    Jperdior\SharedKernel\Domain\Bus\Query\QueryHandler:
        tags: [ { name: messenger.message_handler, bus: messenger.bus.query } ]
    Jperdior\SharedKernel\Domain\Bus\Event\DomainEventSubscriber:
        tags: [ { name: messenger.message_handler, bus: messenger.bus.event } ]
    App\Shared\Presentation\Http\ExceptionStatusMapProvider:
        tags: [ 'app.exception_status_map' ]
```

Adding a new handler = `implements CommandHandler` (or Query/Event). No manual tagging. The same applies to exception status maps: `implements ExceptionStatusMapProvider` in a context's Presentation layer and the Shared `ExceptionListener` picks it up (exact exception class → `{status, code, message}`; duplicate class keys across providers fail fast at container build).

## Cross-Context Communication

Contexts communicate through **two** boundary-clean channels, never by importing each other's
internals:

1. **Domain events** (`event.bus`) — one context emits, another *reacts* asynchronously. Use for
   fire-and-forget side effects. See Events & Subscribers below.
2. **Bus-dispatched commands & queries** (`command.bus` / `query.bus`) — one context *acts on* or
   *reads from* another synchronously, in-request. The caller imports the other context's
   `*Command` / `*Query` and its Response DTO (the `PublicMessage` deptrac layer) and dispatches it
   through the bus; the handler resolves on the other side. Use when you need a result now — e.g.
   verifying an entity exists/belongs to the tenant before proceeding.

What stays **private** to a context: its aggregates, repositories, value objects, and its
executable Application classes (`*Handler`, `*UseCase`, `*Subscriber`). You may never import and call
another context's handler or use case directly — that's what the bus is for. **Messages carry
primitives + shared identifier VOs only** (never a producer's domain VO, or the import drags in
internals). `deptrac`'s `PublicMessage` layer (`^App\<Ctx>\Application\*` minus the classes that
implement a bus handler/subscriber interface, minus `*UseCase`) enforces this, mirroring the
`DomainEvent` carve-out.

**Where to dispatch a cross-context message — controller vs use case.** The capability is allowed
everywhere; *where* depends on **why** you query (the Service Layer / Application Service principle —
Fowler, Vernon — presentation stays thin, decisions live in the application layer):

- **Shaping a response** (read-only display data for the HTTP payload) → a **controller** may dispatch
  it. This is presentation work (e.g. re-querying the just-written aggregate for its response DTO).
- **Gating a decision / enforcing a precondition or invariant** (does this write proceed? ownership /
  eligibility / existence) → the **use case** dispatches it, so it holds for every entry point
  (HTTP, console, subscriber — L-008) and keeps business logic out of Presentation. An ownership check
  placed **only** in a controller is a review-**Critical** (heuristic #10 lists "ownership checks" as
  domain/application concerns; the API edge is never the sole enforcement point).
- **If synchronous cross-context reads proliferate**, prefer a **local read model fed by the
  producer's events** over querying it on every request (autonomy over runtime coupling).

## Events & Subscribers

Domain events on `event.bus`: one context emits; another reacts. Full model in
`docs/domain-events.md`; scaffold with `/add-event-subscriber`.

- **Aggregates record, use cases publish.** `$this->record(new <Event>(...))` in the
  aggregate; `$this->eventBus->publish(...$aggregate->pullDomainEvents())` in the use case.
- **Events live in their owning context's `Domain/Event/`** — always (`UserRegistered` →
  `App\User\Domain\Event\UserRegistered`). A context's events are its **published contract**:
  another context imports the event class directly. deptrac's `DomainEvent` layer
  (`deptrac.yaml`) permits importing any `App\<Context>\Domain\Event\*` while still failing the
  build on any cross-context import of aggregates, repositories, value objects, or **executable**
  Application classes (`*Handler`/`*UseCase`/`*Subscriber`). **Cross-context event payloads must be
  primitive** (no producer value objects), or the import drags in the producer's internals.
- **Subscribers live in the consumer's `Application/<Action>/`** next to the use case they
  drive, named `<Verb><Thing>On<Event>`. They implement `DomainEventSubscriber`
  (`subscribedTo()` + `__invoke`) and **invoke the use case directly** — the same use case the
  `CommandHandler` invokes — mapping the event's primitive payload to its input and generating
  any ids (the subscriber is the composition root when there's no controller). They hold **no**
  business logic and do **not** dispatch a command through the command bus to reach their own use
  case. Auto-tagged onto `event.bus` via `_instanceof` — never tag manually.

```php
final readonly class CreateTenantOnUserRegistered implements DomainEventSubscriber
{
    public function __construct(private CreateTenantUseCase $useCase) {}

    public static function subscribedTo(): array
    {
        return [UserRegistered::class];   // App\User\Domain\Event\UserRegistered — imported directly
    }

    public function __invoke(UserRegistered $event): void
    {
        ($this->useCase)(new CreateTenantCommand(
            tenantId: TenantId::random()->value,   // ids generated here (composition root)
            ownerUserId: $event->aggregateId,
        ));
    }
}
```

One use case, many entry points: the `CommandHandler` (HTTP/CLI) and this subscriber (event)
both invoke `CreateTenantUseCase`; the subscriber never routes through the command handler.

`event.bus` sets `allow_no_handlers: true` (an event with no subscriber is fine) and runs
**synchronously**; `messenger.yaml` has the commented RabbitMQ path for async. Design
reactions to be idempotent so an at-least-once queue is a drop-in later.

## Repository Wiring Pattern

```yaml
# config/services.yaml
App\<Context>\Domain\<Context>Repository:
    alias: App\<Context>\Infrastructure\Persistence\Doctrine<Context>Repository
```

## Doctrine Mapping Registration

```yaml
# config/packages/doctrine.yaml
doctrine:
    orm:
        auto_mapping: false
        mappings:
            <Context>:
                type: attribute
                is_bundle: false
                dir: '%kernel.project_dir%/src/<Context>/Infrastructure/Persistence/Doctrine'
                prefix: 'App\<Context>\Infrastructure\Persistence\Doctrine'
                alias: <Context>
```

## Adding a New Context — Checklist

1. Use `/scaffold-bounded-context` (or copy the User layout manually).
2. Add the new context's mapping under `doctrine.yaml`.
3. Add the repository alias under `services.yaml`.
4. Add the context to `deptrac.yaml`: a layer (a `bool` collector matching `^App\<Context>\.*`
   with `must_not` excluding `^App\<Context-or-any>\Domain\Event\.*`, mirroring `User`) plus a
   ruleset entry allowing `Shared, SharedKernel, Symfony, Doctrine, Vendor, DomainEvent`.
5. Run `make migrate-diff`, review SQL, commit.
6. Write functional tests under `tests/Functional/<Context>/` — one `It<Scenario>Test` per case, extending a `Base<UseCase>Test`.
7. Update root `AGENTS.md` Task Router if the context introduces new task patterns.
