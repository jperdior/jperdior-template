---
name: add-query
description: Add a CQRS read query + handler + response DTO to an existing bounded context. Triggers on "add query", "new query", "scaffold query".
---

# Add Query

Add a read-side query, handler, and response DTO.

## Workflow

1. **Identify the context**.
2. **Name the query**: `GetNote`, `ListNotes`, `FindSubscriptionByCustomer`. Suffix: `Query`.
3. **Generate**:

```
apps/api/src/<Context>/Application/<Verb>/
├── <Verb>Query.php             ← final readonly, implements Shared\Domain\Bus\Query\Query
├── <Verb>QueryHandler.php      ← implements Shared\Domain\Bus\Query\QueryHandler
└── <Verb>Response.php          ← final readonly DTO returned to the caller
```

The Application layer is grouped **by use case, not by trigger** — one folder per action, no
`Command/`/`Query/` grouping folder.

4. **Read directly from a Doctrine repository** (no domain aggregate hydration needed for queries — return DTOs).
5. **Test** — one class per scenario, named `It<Scenario>Test`, under `apps/api/tests/Functional/<Context>/Application/<Verb>/`, extending an abstract `Base<Verb>Test`. AAA (enforced by `FunctionalTestCase`): `arrange()` seeds data, `act()` dispatches the query through the `QueryBus` (no page object), `assert()` checks the returned Response DTO. Only `It*Test` classes are collected.

## Templates

```php
<?php
declare(strict_types=1);

namespace App\<Context>\Application\<Verb>;

use App\Shared\Domain\Bus\Query\Query;

final readonly class <Verb>Query implements Query
{
    public function __construct(
        public string $id,
    ) {
    }
}
```

```php
<?php
declare(strict_types=1);

namespace App\<Context>\Application\<Verb>;

use App\Shared\Domain\Bus\Query\QueryHandler;

final readonly class <Verb>QueryHandler implements QueryHandler
{
    public function __construct(
        private SomeReadRepository $reader,
    ) {
    }

    public function __invoke(<Verb>Query $query): <Verb>Response
    {
        $row = $this->reader->findById($query->id);
        if ($row === null) {
            throw new \DomainException('Not found.');
        }
        return new <Verb>Response(
            id: $row['id'],
            title: $row['title'],
            // ...
        );
    }
}
```

```php
<?php
declare(strict_types=1);

namespace App\<Context>\Application\<Verb>;

final readonly class <Verb>Response
{
    public function __construct(
        public string $id,
        public string $title,
    ) {
    }
}
```

## Rules

- **Queries never mutate.** Read-only.
- **Return DTOs**, never entities or aggregates. Aggregates are write-side citizens.
- **No event emission** from query handlers.
- **Final readonly** on Query and Response.
- **Auto-tagged** via `_instanceof: App\Shared\Domain\Bus\Query\QueryHandler` on the `query.bus`.

## Output

```
✅ Query added: <Context>/<Verb>
   Files: 3 (+ 1 test)
   Bus: query.bus
   Next: /add-route to expose it via HTTP
```
