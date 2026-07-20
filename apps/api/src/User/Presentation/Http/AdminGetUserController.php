<?php

declare(strict_types=1);

namespace App\User\Presentation\Http;

use App\User\Application\GetUserById\GetUserByIdQuery;
use App\User\Application\GetUserById\UserDetailResponse;
use App\User\Domain\Exception\UserNotFound;
use Jperdior\SharedKernel\Domain\Bus\Query\QueryBus;
use OpenApi\Attributes as OA;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/api/admin/users/{id}', name: 'api_admin_get_user', methods: ['GET'])]
#[IsGranted('ROLE_ADMIN')]
final class AdminGetUserController
{
    public function __construct(private readonly QueryBus $queryBus)
    {
    }

    #[OA\Tag(name: 'Admin')]
    #[OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'string', format: 'uuid'))]
    #[OA\Response(
        response: 200,
        description: 'User detail.',
        content: new OA\JsonContent(properties: [
            new OA\Property(property: 'id', type: 'string', format: 'uuid'),
            new OA\Property(property: 'email', type: 'string', format: 'email'),
            new OA\Property(property: 'roles', type: 'array', items: new OA\Items(type: 'string')),
            new OA\Property(property: 'createdAt', type: 'string', format: 'date-time'),
            new OA\Property(property: 'mustResetPassword', type: 'boolean'),
            new OA\Property(property: 'deletedAt', type: 'string', format: 'date-time', nullable: true),
        ]),
    )]
    #[OA\Response(response: 404, description: 'User not found.')]
    public function __invoke(string $id): JsonResponse
    {
        try {
            /** @var UserDetailResponse $response */
            $response = $this->queryBus->ask(new GetUserByIdQuery($id));
        } catch (UserNotFound) {
            throw new NotFoundHttpException('User not found.');
        }

        return new JsonResponse([
            'id' => $response->id,
            'email' => $response->email,
            'roles' => $response->roles,
            'createdAt' => $response->createdAt,
            'mustResetPassword' => $response->mustResetPassword,
            'deletedAt' => $response->deletedAt,
        ]);
    }
}
