<?php

declare(strict_types=1);

namespace App\User\Presentation\Http;

use App\User\Application\Command\UpdateUserRoles\UpdateUserRolesCommand;
use App\User\Domain\Exception\UserNotFound;
use App\User\Presentation\Http\Dto\UpdateUserRolesRequest;
use Jperdior\SharedKernel\Domain\Bus\Command\CommandBus;
use OpenApi\Attributes as OA;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/api/admin/users/{id}/roles', name: 'api_admin_update_user_roles', methods: ['PATCH'])]
#[IsGranted('ROLE_ADMIN')]
final class AdminUpdateUserRolesController
{
    public function __construct(private readonly CommandBus $commandBus)
    {
    }

    #[OA\Tag(name: 'Admin')]
    #[OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'string', format: 'uuid'))]
    #[OA\RequestBody(
        required: true,
        content: new OA\JsonContent(
            required: ['roles'],
            properties: [
                new OA\Property(property: 'roles', type: 'array', items: new OA\Items(type: 'string')),
            ],
        ),
    )]
    #[OA\Response(response: 204, description: 'Roles updated.')]
    #[OA\Response(response: 404, description: 'User not found.')]
    public function __invoke(string $id, #[MapRequestPayload] UpdateUserRolesRequest $request): JsonResponse
    {
        try {
            $this->commandBus->dispatch(new UpdateUserRolesCommand(
                userId: $id,
                roles: $request->roles,
            ));
        } catch (UserNotFound) {
            throw new NotFoundHttpException('User not found.');
        }

        return new JsonResponse(null, 204);
    }
}
