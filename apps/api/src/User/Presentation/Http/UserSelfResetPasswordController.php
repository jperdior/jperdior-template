<?php

declare(strict_types=1);

namespace App\User\Presentation\Http;

use App\User\Application\SelfResetPassword\SelfResetPasswordCommand;
use App\User\Infrastructure\Symfony\Security\SecurityUser;
use App\User\Presentation\Http\Dto\SelfResetPasswordRequest;
use Jperdior\SharedKernel\Domain\Bus\Command\CommandBus;
use OpenApi\Attributes as OA;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/api/users/me/reset-password', name: 'api_user_self_reset_password', methods: ['POST'])]
#[IsGranted('IS_AUTHENTICATED_FULLY')]
final class UserSelfResetPasswordController
{
    public function __construct(
        private readonly CommandBus $commandBus,
        private readonly Security $security,
    ) {
    }

    #[OA\Tag(name: 'User')]
    #[OA\RequestBody(
        required: true,
        content: new OA\JsonContent(
            required: ['newPassword'],
            properties: [
                new OA\Property(property: 'newPassword', type: 'string', minLength: 8),
            ],
        ),
    )]
    #[OA\Response(response: 204, description: 'Password reset successfully.')]
    public function __invoke(#[MapRequestPayload] SelfResetPasswordRequest $request): JsonResponse
    {
        $securityUser = $this->security->getUser();
        $userId = $securityUser instanceof SecurityUser ? $securityUser->getId() : '';

        $this->commandBus->dispatch(new SelfResetPasswordCommand(
            userId: $userId,
            newPassword: $request->newPassword,
        ));

        return new JsonResponse(null, 204);
    }
}
