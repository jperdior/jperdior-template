<?php

declare(strict_types=1);

namespace App\User\Presentation\Http;

use App\User\Application\Query\ListUsers\ListUsersQuery;
use App\User\Application\Query\ListUsers\UserListResponse;
use Jperdior\SharedKernel\Domain\Bus\Query\QueryBus;
use OpenApi\Attributes as OA;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/api/admin/users', methods: ['GET'])]
#[IsGranted('ROLE_ADMIN')]
final class AdminListUsersController
{
    public function __construct(private readonly QueryBus $queryBus)
    {
    }

    #[OA\Tag(name: 'Admin')]
    #[OA\Parameter(name: 'limit', in: 'query', schema: new OA\Schema(type: 'integer', minimum: 1, maximum: 100, default: 50))]
    #[OA\Parameter(name: 'offset', in: 'query', schema: new OA\Schema(type: 'integer', minimum: 0, default: 0))]
    #[OA\Response(
        response: 200,
        description: 'Paginated list of every user.',
        content: new OA\JsonContent(properties: [
            new OA\Property(property: 'total', type: 'integer'),
            new OA\Property(property: 'users', type: 'array', items: new OA\Items(properties: [
                new OA\Property(property: 'id', type: 'string', format: 'uuid'),
                new OA\Property(property: 'email', type: 'string', format: 'email'),
                new OA\Property(property: 'roles', type: 'array', items: new OA\Items(type: 'string')),
                new OA\Property(property: 'createdAt', type: 'string', format: 'date-time'),
            ])),
        ]),
    )]
    #[OA\Response(response: 403, description: 'Caller is not an admin.')]
    public function __invoke(Request $request): JsonResponse
    {
        /** @var UserListResponse $response */
        $response = $this->queryBus->ask(new ListUsersQuery(
            limit: (int) $request->query->get('limit', 50),
            offset: (int) $request->query->get('offset', 0),
        ));

        return new JsonResponse([
            'total' => $response->total,
            'users' => array_map(static fn ($u) => [
                'id' => $u->id,
                'email' => $u->email,
                'roles' => $u->roles,
                'createdAt' => $u->createdAt,
            ], $response->users),
        ]);
    }
}
