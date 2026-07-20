<?php

declare(strict_types=1);

namespace App\User\Presentation\Http;

use App\User\Application\ForcePasswordReset\ForcePasswordResetCommand;
use App\User\Domain\Exception\UserNotFound;
use Jperdior\SharedKernel\Domain\Bus\Command\CommandBus;
use OpenApi\Attributes as OA;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/api/admin/users/{id}/force-password-reset', name: 'api_admin_force_password_reset', methods: ['POST'])]
#[IsGranted('ROLE_ADMIN')]
final class AdminForcePasswordResetController
{
    public function __construct(private readonly CommandBus $commandBus)
    {
    }

    #[OA\Tag(name: 'Admin')]
    #[OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'string', format: 'uuid'))]
    #[OA\Response(response: 204, description: 'Password reset forced.')]
    #[OA\Response(response: 404, description: 'User not found.')]
    public function __invoke(string $id): JsonResponse
    {
        try {
            $this->commandBus->dispatch(new ForcePasswordResetCommand(userId: $id));
        } catch (UserNotFound) {
            throw new NotFoundHttpException('User not found.');
        }

        return new JsonResponse(null, 204);
    }
}
