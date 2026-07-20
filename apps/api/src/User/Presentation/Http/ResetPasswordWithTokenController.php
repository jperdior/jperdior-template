<?php

declare(strict_types=1);

namespace App\User\Presentation\Http;

use App\User\Application\ResetPasswordWithToken\ResetPasswordWithTokenCommand;
use App\User\Presentation\Http\Dto\ResetPasswordWithTokenRequest;
use Jperdior\SharedKernel\Domain\Bus\Command\CommandBus;
use OpenApi\Attributes as OA;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\RateLimiter\RateLimiterFactory;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/auth/reset-password', name: 'api_user_reset_password_with_token', methods: ['POST'])]
final class ResetPasswordWithTokenController
{
    public function __construct(
        private readonly CommandBus $commandBus,
        // Autowired by argument name: matches the `reset_password.limiter` service from rate_limiter.yaml.
        private readonly RateLimiterFactory $resetPasswordLimiter,
    ) {
    }

    #[OA\Tag(name: 'Auth')]
    #[OA\RequestBody(
        required: true,
        content: new OA\JsonContent(
            required: ['token', 'newPassword'],
            properties: [
                new OA\Property(property: 'token', type: 'string', pattern: '^[a-f0-9]{96}$'),
                new OA\Property(property: 'newPassword', type: 'string', minLength: 8),
            ],
        ),
    )]
    #[OA\Response(response: 204, description: 'Password reset successfully.')]
    #[OA\Response(response: 404, description: 'Token not found.')]
    #[OA\Response(response: 422, description: 'Token expired or already used; weak password.')]
    #[OA\Response(response: 429, description: 'Rate limit exceeded.')]
    public function __invoke(
        Request $request,
        #[MapRequestPayload]
        ResetPasswordWithTokenRequest $payload,
    ): JsonResponse {
        $limit = $this->resetPasswordLimiter->create($request->getClientIp() ?? 'unknown')->consume(1);
        if (!$limit->isAccepted()) {
            return new JsonResponse(
                ['code' => 'RATE_LIMITED', 'message' => 'Too many password reset attempts. Please try again later.'],
                429,
            );
        }

        // Token failures are mapped to 404/422 by the ExceptionListener via UserExceptionStatusMap.
        $this->commandBus->dispatch(new ResetPasswordWithTokenCommand(
            token: $payload->token,
            newPassword: $payload->newPassword,
        ));

        return new JsonResponse(null, 204);
    }
}
