---
name: add-route
description: Add an HTTP endpoint (invokable controller + Request DTO + Response DTO + Nelmio OpenAPI annotation + functional test) to a bounded context. Triggers on "add route", "add endpoint", "new HTTP route".
---

# Add Route

Add a single HTTP endpoint backed by an existing Command or Query in a bounded context.

## Workflow

1. **Identify the bounded context** and the **Command or Query** the route dispatches to. If neither exists yet, run `/add-command` or `/add-query` first.
2. **Name the controller** as `<Verb><Aggregate>Controller` (invokable). One controller per route.
3. **Pick the HTTP method**:
   - Commands → `POST` (create), `PATCH` (update), `DELETE` (delete).
   - Queries → `GET`.
4. **Generate**:

```
apps/api/src/<Context>/Presentation/Http/
├── <Verb><Aggregate>Controller.php
└── Dto/<Verb><Aggregate>Request.php          ← if the route accepts a body
```

5. **Add the Nelmio OpenAPI annotation** on the controller.
6. **Run `make migrate-diff`** only if the route requires new schema.
7. **Add a functional test** under `apps/api/tests/Functional/<Context>/Presentation/Http/<Verb><Aggregate>ControllerTest.php`.

## Controller Template (Command)

```php
<?php
declare(strict_types=1);

namespace App\<Context>\Presentation\Http;

use App\<Context>\Application\Command\<Verb>\<Verb>Command;
use App\<Context>\Presentation\Http\Dto\<Verb><Aggregate>Request;
use App\Shared\Domain\Bus\Command\CommandBus;
use Nelmio\ApiDocBundle\Annotation\Model;
use OpenApi\Attributes as OA;
use Symfony\Bridge\Doctrine\Attribute\MapEntity;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\Uid\Uuid;

#[Route('/api/<resource>', methods: ['POST'])]
#[IsGranted('IS_AUTHENTICATED_FULLY')]
final class <Verb><Aggregate>Controller
{
    public function __construct(
        private readonly CommandBus $commandBus,
    ) {
    }

    #[OA\Response(response: 201, description: 'Created.')]
    #[OA\RequestBody(content: new OA\JsonContent(ref: new Model(type: <Verb><Aggregate>Request::class)))]
    public function __invoke(#[MapRequestPayload] <Verb><Aggregate>Request $request): JsonResponse
    {
        $id = Uuid::v4()->toRfc4122();
        $this->commandBus->dispatch(new <Verb>Command(
            id: $id,
            title: $request->title,
        ));

        return new JsonResponse(['id' => $id], 201);
    }
}
```

## Request DTO Template

```php
<?php
declare(strict_types=1);

namespace App\<Context>\Presentation\Http\Dto;

use Symfony\Component\Validator\Constraints as Assert;

final readonly class <Verb><Aggregate>Request
{
    public function __construct(
        #[Assert\NotBlank]
        #[Assert\Length(max: 200)]
        public string $title,
    ) {
    }
}
```

## Rules

- **One controller per route**, invokable (`__invoke`).
- **`#[IsGranted(...)]`** on every protected endpoint. Anonymous endpoints under `/auth/*` only.
- **Nelmio annotations are mandatory** — the OpenAPI doc drives the TS client generation.
- **HTTP status codes**: 201 (create), 200 (read/update), 204 (delete), 400 (bad request), 401 (auth required), 403 (forbidden), 404 (not found), 409 (conflict).
- **Errors** thrown by handlers (Domain exceptions) are mapped to HTTP status by an exception listener — never catch them in the controller unless you have a specific reason.
- **No `em.find()` in controllers** — go through the bus.

## Output

```
✅ Route added: {METHOD} /api/{path}
   Controller: <Context>/Presentation/Http/<Verb><Aggregate>Controller
   Wired to: command.bus or query.bus
   OpenAPI: annotated
   Test: <Verb><Aggregate>ControllerTest.php
   Next: regenerate the TS client with `make gen-api`
```
