# apps/api — Agents Guidelines

Symfony 7.4 modular monolith. One app, many bounded contexts under `src/<Context>/`. Single image, two runtime processes: `api` (php-fpm) and `worker` (`messenger:consume async`).

## Always

- Match the four-layer layout in every context: `Domain/`, `Application/`, `Infrastructure/`, `Presentation/`.
- Dispatch through `CommandBus` / `QueryBus` from controllers. Never inject a handler.
- Place repository interfaces in `Domain/`, Doctrine implementations in `Infrastructure/Persistence/`, alias them in `config/services.yaml`.
- Use **XML** Doctrine mapping under `Infrastructure/Persistence/Doctrine/Mapping/`.
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
- Ask before adding a tenancy concern (route via `packages/tenancy-php` only).

## Never

- **Never** import another context's `Domain/` or `Application/`. CI `deptrac` enforces this.
- **Never** add `#[ORM\*]` attributes to domain entities. XML only.
- **Never** add `tenant_id` columns to entities here. Tenancy is in `packages/tenancy-php`.
- **Never** call `em->find()` from a controller. Use a query.
- **Never** catch a domain exception in a controller unless transforming it to a specific HTTP status with rationale.
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
│       ├── doctrine.yaml          ← XML mapping per context
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
│   │   └── Presentation/Http/ExceptionListener.php
│   ├── User/                      ← bounded context: auth
│   ├── Note/                      ← bounded context: hello-world
│   └── <NextContext>/             ← drop a folder, get a context
└── tests/
    ├── Unit/
    ├── Functional/
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
```

Adding a new handler = `implements CommandHandler` (or Query/Event). No manual tagging.

## Repository Wiring Pattern

```yaml
# config/services.yaml
App\Note\Domain\NoteRepository:
    alias: App\Note\Infrastructure\Persistence\DoctrineNoteRepository
```

## Doctrine Mapping Registration

```yaml
# config/packages/doctrine.yaml
doctrine:
    orm:
        auto_mapping: false
        mappings:
            Note:
                type: xml
                is_bundle: false
                dir: '%kernel.project_dir%/src/Note/Infrastructure/Persistence/Doctrine/Mapping'
                prefix: 'App\Note\Domain'
                alias: Note
```

## Adding a New Context — Checklist

1. Use `/scaffold-bounded-context` (or copy the User/Note layout manually).
2. Add the new context's mapping under `doctrine.yaml`.
3. Add the repository alias under `services.yaml`.
4. Run `make migrate-diff`, review SQL, commit.
5. Write functional tests under `tests/Functional/<Context>/`.
6. Update root `AGENTS.md` Task Router if the context introduces new task patterns.
