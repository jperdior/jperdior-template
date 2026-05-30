# Architecture

## TL;DR

- **Modular monolith** in PHP 8.4 / Symfony 7.4. One API app (`apps/api`), many bounded contexts inside it.
- **DDD + Hexagonal + CQRS.** Four-layer bounded contexts; three Symfony Messenger buses.
- **XML Doctrine mapping** — no ORM attributes on domain entities.
- **JWT auth** with single-use refresh-token rotation. Single-tenant.
- **Frontends** are Next.js 15 (App Router) consuming the API via a generated TS client.
- **Strict cross-context boundaries** enforced by `deptrac` in CI.

---

## High-Level Diagram

```
┌────────────────────────┐         ┌────────────────────────┐
│ apps/web (Next.js 15)  │         │ apps/admin (Next.js 15)│
└──────────┬─────────────┘         └──────────┬─────────────┘
           │                                  │
           └────────────► REST / JSON ◄───────┘
                              │
                              ▼
                  ┌───────────────────────┐
                  │   apps/api (Symfony)  │
                  │                       │
                  │  ┌──────┐ ┌────────┐  │
                  │  │ User │ │  ...   │  │  ← bounded contexts
                  │  └──────┘ └────────┘  │
                  └────────┬──────────────┘
                           │
              ┌────────────┼─────────────┐
              ▼            ▼             ▼
        PostgreSQL 16   Redis 7      Messenger
        (Doctrine 3)   (cache)    (sync — in-process)
```

One image, one process by default. New bounded contexts drop a folder under `apps/api/src/<Context>/` — no new infra, no new image, no new database. When you need async processing, add a transport in `messenger.yaml` and route specific commands to it — see the commented block in that file.

---

## Bounded Context Layout

Every context under `apps/api/src/<Context>/` follows the same four-layer structure:

```
<Context>/
├── Domain/                          ← pure PHP, zero framework deps
│   ├── <Aggregate>.php              ← extends AggregateRoot
│   ├── <Aggregate>Id.php            ← UUID value object
│   ├── <Aggregate>Repository.php    ← repository interface (port)
│   ├── <ValueObject>.php            ← readonly value objects
│   ├── Event/<Aggregate>Created.php ← domain events
│   └── Exception/<Aggregate>NotFound.php
│
├── Application/                     ← CQRS use cases (no framework deps)
│   ├── Command/<Verb>/
│   │   ├── <Verb>Command.php        ← readonly DTO
│   │   ├── <Verb>CommandHandler.php ← implements CommandHandler
│   │   └── <Verb>UseCase.php        ← business logic (optional thin layer)
│   └── Query/<Verb>/
│       ├── <Verb>Query.php          ← readonly DTO
│       ├── <Verb>QueryHandler.php   ← implements QueryHandler
│       ├── <Verb>UseCase.php
│       └── <Verb>Response.php       ← readonly DTO
│
├── Infrastructure/                  ← Symfony/Doctrine adapters
│   ├── Persistence/
│   │   ├── Doctrine<Aggregate>Repository.php
│   │   └── Doctrine/
│   │       ├── Mapping/<Aggregate>.orm.xml
│   │       └── Type/<ValueObject>Type.php   ← custom DBAL types
│   └── Symfony/
│       ├── Resources/config/services.yaml   ← repository alias
│       └── Console/<Command>Command.php
│
└── Presentation/                    ← HTTP endpoints, CLI commands
    └── Http/
        ├── <Verb><Aggregate>Controller.php
        └── Dto/<Verb><Aggregate>Request.php
```

### The Four Rules

**1. Domain imports nothing framework-specific.**
No `Symfony\*`, `Doctrine\*`, `Predis\*` in `Domain/`. The domain is a pure object model.

**2. Controllers dispatch via the bus.**
Inject `CommandBus` / `QueryBus`, never handlers directly. Controllers are thin — they validate input, build a Command/Query, dispatch it, and format the response.

**3. Repository interfaces in `Domain/`, implementations in `Infrastructure/`.**
The domain defines the contract. Doctrine implements it. The alias lives in `src/<Context>/Infrastructure/Symfony/Resources/config/services.yaml`.

**4. No cross-context imports.**
`App\User\Domain\` is invisible to `App\Order\`. Contexts communicate through domain events on the event bus or through public Application service responses. `deptrac` enforces this in CI — a cross-context `Domain/` import fails the build.

---

## CQRS — Three Buses

Symfony Messenger is configured with three separate buses:

| Bus | Interface to implement | Default transport | When to go async |
|-----|----------------------|-------------------|-----------------|
| `command.bus` | `CommandHandler` | `sync` | Slow writes (email, external API calls) |
| `query.bus` | `QueryHandler` | `sync` (always) | Never |
| `event.bus` | `DomainEventSubscriber` | `sync` | Slow side-effects |

`config/services.yaml` auto-wires every implementer via `_instanceof`:

```yaml
_instanceof:
    Jperdior\SharedKernel\Domain\Bus\Command\CommandHandler:
        tags: [{ name: messenger.message_handler, bus: messenger.bus.command }]
    Jperdior\SharedKernel\Domain\Bus\Query\QueryHandler:
        tags: [{ name: messenger.message_handler, bus: messenger.bus.query }]
    Jperdior\SharedKernel\Domain\Bus\Event\DomainEventSubscriber:
        tags: [{ name: messenger.message_handler, bus: messenger.bus.event }]
```

Adding a new handler = `implements CommandHandler`. No YAML changes needed.

### Command flow

```
Controller
  → CommandBus::dispatch(CreateUserCommand)
    → Messenger routes to CreateUserCommandHandler
      → handler delegates to CreateUserUseCase
        → UseCase calls UserRepository::save(user)
          → user.pullDomainEvents() → EventBus::publish(UserRegistered)
```

### Query flow

```
Controller
  → QueryBus::ask(GetCurrentUserQuery)
    → Messenger routes to GetCurrentUserQueryHandler
      → handler calls GetCurrentUserUseCase
        → UseCase fetches from UserRepository, returns CurrentUserResponse
      ← CurrentUserResponse (readonly DTO)
    ← CurrentUserResponse
  ← JSON response
```

---

## Custom DBAL Types

Value objects are mapped to their primitive DB representations via custom Doctrine DBAL types. This keeps the domain clean (entities always hold value objects) and prevents PHP 8.4 lazy-ghost hydration errors.

Example pattern (`UserIdType`):

```php
final class UserIdType extends Type
{
    public function convertToPHPValue(mixed $value, AbstractPlatform $platform): ?UserId
    {
        return null !== $value ? UserId::fromString((string) $value) : null;
    }

    public function convertToDatabaseValue(mixed $value, AbstractPlatform $platform): ?string
    {
        return $value instanceof UserId ? $value->value : null;
    }

    public function getSQLDeclaration(array $column, AbstractPlatform $platform): string
    {
        return $platform->getStringTypeDeclarationSQL($column);
    }
}
```

Types are registered in `config/packages/doctrine.yaml` and referenced in the XML mapping.

---

## Auth

- **Stateless JWT** (RS256) via `lexik/jwt-authentication-bundle`.
- **Single-use refresh-token rotation** via `gesdinet/jwt-refresh-token-bundle`.
- Every `/auth/refresh` call issues a new refresh token and revokes the old one atomically. Reusing a revoked token logs the user out everywhere.
- **Passwords**: argon2id via Symfony's `password_hasher`.
- The `User` context is a normal bounded context — not special-cased in the framework.

See [auth.md](auth.md) for the full flow including the frontend cookie strategy.

---

## Persistence

- **PostgreSQL 16**. Doctrine 3 with `underscore_number_aware` naming strategy.
- **UUID v4 primary keys** (generated at the application layer, not the DB).
- **XML mapping only**. No `#[ORM\*]` attributes on domain entities — the domain is framework-agnostic.
- **Migrations** under `apps/api/migrations/`. Generated with `make migrate-diff`, reviewed manually, applied with `make migrate`.
- **One aggregate root per write transaction.** Use `TransactionInterface` from `shared-kernel-php` when you need to span aggregates (rare).
- **Read DTOs in queries.** Query handlers return readonly response objects, not hydrated aggregates.

---

## Frontends

Both `apps/web` and `apps/admin` are Next.js 15 App Router applications:

- **Server Components by default.** `"use client"` is used only when browser APIs, interactivity, or state management require it.
- **Server Actions** for mutations.
- **Forms**: shadcn `Form` + react-hook-form + zod.
- **API access**: `@jperdior/api-client-ts` (generated from OpenAPI spec). Never raw `fetch`.
- **Tokens**: access token in memory (Zustand), refresh token in HttpOnly cookie. Next.js middleware handles silent refresh on 401.
- **Design tokens**: semantic Tailwind tokens from `@jperdior/ui-react`. No hardcoded colors. See `.ai/ds-rules.md`.

---

## Ops

- **One process**: `api` (nginx + php-fpm). No worker container — Messenger buses run synchronously.
- **Docker Compose** is the primary dev and deployment path. A Helm chart skeleton is included under `ops/k8s/` for Kubernetes.
- **CI** runs on every PR: `make lint` → `make test` → `make build-web` → (optional) `make test-e2e`.

See [ops.md](ops.md) for the full environment reference.

---

## Why Modular Monolith

Boundaries are enforced in code (`deptrac`), not on the network. Adding a context means dropping a folder — no new infra, no new image, no new database. The single Postgres connection pool makes transactions trivial.

When a context needs to be extracted later, it's already communicating via async commands and events. The mechanical change is swapping in-process bus dispatch for an HTTP or AMQP bridge. The domain code doesn't change.

**Extract a context when:**
- It generates > 50% of traffic and starves others.
- Hard regulatory data-residency requirements force a separate DB.
- Team ownership lines are clear and merge contention is real (15+ engineers).

Until any of those: one app, many contexts.
