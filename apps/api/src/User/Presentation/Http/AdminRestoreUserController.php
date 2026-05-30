<?php

declare(strict_types=1);

namespace App\User\Presentation\Http;

use App\User\Application\Command\RestoreUser\RestoreUserCommand;
use App\User\Domain\Exception\UserNotFound;
use Jperdior\SharedKernel\Domain\Bus\Command\CommandBus;
use OpenApi\Attributes as OA;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/api/admin/users/{id}/restore', name: 'api_admin_restore_user', methods: ['POST'])]
#[IsGranted('ROLE_ADMIN')]
final class AdminRestoreUserController
{
    public function __construct(private readonly CommandBus $commandBus)
    {
    }

    #[OA\Tag(name: 'Admin')]
    #[OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'string', format: 'uuid'))]
    #[OA\Response(response: 204, description: 'User restored.')]
    #[OA\Response(response: 404, description: 'User not found.')]
    public function __invoke(string $id): JsonResponse
    {
        try {
            $this->commandBus->dispatch(new RestoreUserCommand(userId: $id));
        } catch (UserNotFound) {
            throw new NotFoundHttpException('User not found.');
        }

        return new JsonResponse(null, 204);
    }
}
