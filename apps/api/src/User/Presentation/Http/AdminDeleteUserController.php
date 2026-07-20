<?php

declare(strict_types=1);

namespace App\User\Presentation\Http;

use App\User\Application\SoftDeleteUser\SoftDeleteUserCommand;
use App\User\Infrastructure\Symfony\Security\SecurityUser;
use Jperdior\SharedKernel\Domain\Bus\Command\CommandBus;
use OpenApi\Attributes as OA;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/api/admin/users/{id}', name: 'api_admin_delete_user', methods: ['DELETE'])]
#[IsGranted('ROLE_ADMIN')]
final class AdminDeleteUserController
{
    public function __construct(
        private readonly CommandBus $commandBus,
        private readonly Security $security,
    ) {
    }

    #[OA\Tag(name: 'Admin')]
    #[OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'string', format: 'uuid'))]
    #[OA\Response(response: 204, description: 'User soft-deleted.')]
    #[OA\Response(response: 409, description: 'Cannot delete your own account.')]
    public function __invoke(string $id): JsonResponse
    {
        $securityUser = $this->security->getUser();
        $adminId = $securityUser instanceof SecurityUser ? $securityUser->getId() : '';

        $this->commandBus->dispatch(new SoftDeleteUserCommand(
            userId: $id,
            requestingAdminId: $adminId,
        ));

        return new JsonResponse(null, 204);
    }
}
