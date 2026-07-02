# apps/api — Agents Guidelines

Symfony 7.4 modular monolith. One app, many bounded contexts under `src/<Context>/`. Single image, two runtime processes: `api` (php-fpm) and `worker` (`messenger:consume async`).

## Always

- Match the four-layer layout in every context: `Domain/`, `Application/`, `Infrastructure/`, `Presentation/`.
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

- **Never** import another context's `Domain/` or `Application/`. CI `deptrac` enforces this.
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
    ├── Functional/                    ← full-stack HTTP tests against Postgres
    └── bootstrap.php
```

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
4. Run `make migrate-diff`, review SQL, commit.
5. Write functional tests under `tests/Functional/<Context>/`.
6. Update root `AGENTS.md` Task Router if the context introduces new task patterns.
