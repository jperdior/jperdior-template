# Architecture

## TL;DR

- **Modular monolith** in PHP 8.4 / Symfony 7.4. One API app (`apps/api`), many bounded contexts inside it.
- **DDD + Hexagonal + CQRS.** Four-layer bounded contexts; three Symfony Messenger buses.
- **XML Doctrine mapping** — no attributes on domain entities.
- **JWT auth** with refresh-token rotation. Auth is single-tenant by default.
- **Multi-tenancy is opt-in** via `packages/tenancy-php` (SQLFilter + request-scoped TenantContext).
- **Frontends** are Next.js 15 (App Router) — `apps/web` (public) and `apps/admin` (back-office) — consuming the API via a generated TS client.
- **Strict cross-context boundaries** enforced by `deptrac` in CI.

## High-Level Diagram

```
┌────────────────────────┐         ┌────────────────────────┐
│ apps/web (Next.js 15)  │◄────────│ apps/admin (Next.js 15)│
└──────────┬─────────────┘         └──────────┬─────────────┘
           │                                  │
           └────────────► REST / JSON ◄───────┘
                              │
                              ▼
                  ┌───────────────────────┐
                  │  apps/api (Symfony)   │
                  │ ┌──────┐ ┌────────┐   │
                  │ │ User │ │ Note   │ … │  ← bounded contexts
                  │ └──────┘ └────────┘   │
                  └────────┬──────────────┘
                           │
              ┌────────────┼─────────────┐
              ▼            ▼             ▼
        PostgreSQL 16   Redis        Messenger
        (Doctrine 3)   (cache)   (doctrine:// transport)
                                       │
                                       ▼
                              ┌────────────────┐
                              │ worker (PHP)   │  ← same image as `api`,
                              │ messenger:     │    different command
                              │ consume async  │
                              └────────────────┘
```

## Bounded Context Layout

Every context under `apps/api/src/<Context>/` has the same four layers:

```
<Context>/
├── Domain/                  ← pure PHP, no framework deps
│   ├── <Aggregate>.php      ← extends Shared\Domain\Aggregate\AggregateRoot
│   ├── <Aggregate>Id.php    ← value object (Uuid-backed)
│   ├── <Aggregate>Repository.php  ← interface
│   ├── Event/<Aggregate>Created.php
│   └── Exception/<Aggregate>NotFound.php
├── Application/             ← CQRS use cases
│   ├── Command/<Verb>/
│   │   ├── <Verb>Command.php
│   │   └── <Verb>CommandHandler.php
│   └── Query/<Verb>/
│       ├── <Verb>Query.php
│       ├── <Verb>QueryHandler.php
│       └── <Verb>Response.php
├── Infrastructure/          ← adapters
│   └── Persistence/
│       ├── Doctrine<Aggregate>Repository.php
│       └── Doctrine/Mapping/<Aggregate>.orm.xml
└── Presentation/            ← HTTP / CLI
    └── Http/
        ├── <Verb><Aggregate>Controller.php
        └── Dto/<Verb><Aggregate>Request.php
```

### Rules

1. **Domain depends on nothing framework-specific.** No `Symfony\*`, `Doctrine\*`, `Predis\*` imports.
2. **Controllers dispatch via the bus.** They inject `CommandBus` / `QueryBus`, never handlers directly.
3. **Repository interfaces in `Domain/`.** Doctrine implementations in `Infrastructure/Persistence/`. Aliased in `config/services.yaml`.
4. **Doctrine mapping is XML.** No attributes on domain entities.
5. **No cross-context imports.** Communication happens via the event bus or public application services. `deptrac` blocks violations in CI.

## CQRS — Three Buses

Symfony Messenger configures three buses:

| Bus | Interface | Tag (auto via `_instanceof`) | Transport (default) |
|-----|-----------|------------------------------|---------------------|
| `command.bus` | `Shared\Domain\Bus\Command\CommandHandler` | `messenger.bus.command` | `sync` for fast commands, `async` (Doctrine) for slow ones |
| `query.bus`   | `Shared\Domain\Bus\Query\QueryHandler`     | `messenger.bus.query`   | `sync` (always) |
| `event.bus`   | `Shared\Domain\Bus\Event\DomainEventSubscriber` | `messenger.bus.event` | `sync` for fast subscribers, `async` for slow ones |

`config/services.yaml` auto-wires every implementer via `_instanceof`. Adding a new handler requires only `implements CommandHandler`.

## Auth

- **Symfony Security** with the `lexik/jwt-authentication-bundle` and `gesdinet/jwt-refresh-token-bundle`.
- **Stateless** firewall on `/api/*` and `/auth/*`.
- **Refresh-token rotation**: each `/auth/refresh` issues a new refresh token and revokes the previous one in the same transaction. Reuse of a revoked token logs the user out everywhere and emits a security event.
- **Passwords**: argon2id via Symfony's `password_hasher`.
- **Users live in `apps/api/src/User/`** as a normal bounded context — they are not special-cased.

See `docs/auth.md` for the full flow.

## Multi-Tenancy

**Not in core.** The default template is single-tenant. When a project needs multi-tenancy, opt in by:

1. Adding `packages/tenancy-php` to `apps/api/composer.json`.
2. Registering `TenancyBundle` in `config/bundles.php`.
3. Marking the relevant entities with the `TenantOwned` interface.
4. Adding `tenant_id` to those tables via a project-specific migration.
5. Configuring tenant resolution (JWT claim, subdomain, header).

A Doctrine SQLFilter auto-scopes every `TenantOwned` query to the current tenant from the request-scoped `TenantContext`. Core entities are untouched.

See `docs/multitenancy.md`.

## Persistence

- **PostgreSQL 16** in compose. Doctrine 3 with `underscore_number_aware` naming strategy.
- **UUID v4 primary keys** by default; v5 from a business identifier when avoiding cross-source collisions (e.g. when ingesting from external systems).
- **Migrations** under `apps/api/migrations/`. Generated via `make migrate-diff`, reviewed, applied via `make migrate`.
- **One aggregate root per write transaction.** Use the `TransactionInterface` from `shared-kernel-php` for multi-aggregate writes (rare).
- **Read DTOs in queries**, not entities. Avoid lazy-loading surprises.

## Frontends

- **Next.js 15 App Router** in both `apps/web` and `apps/admin`.
- **Server Components by default.** Every `"use client"` is justified in the spec.
- **Forms**: shadcn `Form` + react-hook-form + zod.
- **API access** via `@jperdior/api-client-ts` (generated from the OpenAPI spec). Never raw `fetch`.
- **DS tokens** from `@jperdior/ui-react`'s `globals.css`. No hardcoded colors, no arbitrary text sizes. See `.ai/ds-rules.md`.

## Ops

- **`apps/api` ships as two services**: `api` (nginx + php-fpm) and `worker` (`messenger:consume async`). Same image, different command.
- **Compose** is the default dev/prod path. K8s skeleton is included under `ops/k8s/` but not required.
- **CI** runs on every PR: `make lint` + `make test` + `make build-web` + (conditionally) `make test-e2e`.

## Why Modular Monolith (and not microservices)

- **Boundaries enforced in code** (`deptrac`) — not the network. Cross-context imports are impossible without explicit policy changes.
- **Adding a context is dropping a folder.** No new infra, no new image, no new DB. The `scaffold-bounded-context` skill makes this seconds.
- **Same Postgres, one connection pool, one transactional boundary per command.** Operationally trivial.
- **A context can be extracted later** — because it already communicates via async commands and events, the only mechanical change is swapping in-process bus dispatch for an HTTP/AMQP bridge. The domain doesn't change.

For a hobby template and the vast majority of business apps, **never split**.

## When You'd Outgrow This

- One context generates > 50 % of traffic and starves the others → extract it.
- Hard regulatory data-residency for one context → split its DB or service.
- Team > 15-20 engineers and merge contention → consider splitting along team-ownership lines.

Until then: one app, many contexts.
