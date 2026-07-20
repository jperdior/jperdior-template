<?php

declare(strict_types=1);

namespace App\User\Presentation\Http;

use App\User\Application\RequestPasswordRecovery\RequestPasswordRecoveryCommand;
use App\User\Presentation\Http\Dto\ForgotPasswordRequest;
use Jperdior\SharedKernel\Domain\Bus\Command\CommandBus;
use OpenApi\Attributes as OA;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\RateLimiter\RateLimiterFactory;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/auth/forgot-password', name: 'api_user_forgot_password', methods: ['POST'])]
final class ForgotPasswordController
{
    public function __construct(
        private readonly CommandBus $commandBus,
        // Autowired by argument name: matches the `forgot_password.limiter` service from rate_limiter.yaml.
        private readonly RateLimiterFactory $forgotPasswordLimiter,
    ) {
    }

    #[OA\Tag(name: 'Auth')]
    #[OA\RequestBody(
        required: true,
        content: new OA\JsonContent(
            required: ['email'],
            properties: [
                new OA\Property(property: 'email', type: 'string', format: 'email'),
            ],
        ),
    )]
    #[OA\Response(response: 204, description: 'Always returned regardless of whether the email is registered (BR-U05).')]
    #[OA\Response(response: 422, description: 'Malformed email.')]
    #[OA\Response(response: 429, description: 'Rate limit exceeded.')]
    public function __invoke(
        Request $request,
        #[MapRequestPayload]
        ForgotPasswordRequest $payload,
    ): JsonResponse {
        $limit = $this->forgotPasswordLimiter->create($request->getClientIp() ?? 'unknown')->consume(1);
        if (!$limit->isAccepted()) {
            return new JsonResponse(
                ['code' => 'RATE_LIMITED', 'message' => 'Too many password recovery requests. Please try again later.'],
                429,
            );
        }

        $this->commandBus->dispatch(new RequestPasswordRecoveryCommand(email: $payload->email));

        return new JsonResponse(null, 204);
    }
}
