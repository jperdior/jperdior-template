<?php

declare(strict_types=1);

namespace App\User\Presentation\Http;

use App\User\Application\Command\SignUp\SignUpCommand;
use App\User\Presentation\Http\Dto\SignUpRequest;
use Jperdior\SharedKernel\Domain\Bus\Command\CommandBus;
use OpenApi\Attributes as OA;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Uid\Uuid;

#[Route('/auth/signup', name: 'api_user_signup', methods: ['POST'])]
final class SignUpController
{
    public function __construct(private readonly CommandBus $commandBus)
    {
    }

    #[OA\Tag(name: 'Auth')]
    #[OA\RequestBody(
        required: true,
        content: new OA\JsonContent(
            required: ['email', 'password'],
            properties: [
                new OA\Property(property: 'email', type: 'string', format: 'email'),
                new OA\Property(property: 'password', type: 'string', minLength: 8),
            ],
        ),
    )]
    #[OA\Response(
        response: 201,
        description: 'User created.',
        content: new OA\JsonContent(properties: [
            new OA\Property(property: 'id', type: 'string', format: 'uuid'),
        ]),
    )]
    #[OA\Response(response: 409, description: 'Email already in use.')]
    public function __invoke(#[MapRequestPayload] SignUpRequest $request): JsonResponse
    {
        $id = Uuid::v4()->toRfc4122();
        $this->commandBus->dispatch(new SignUpCommand(
            id: $id,
            email: $request->email,
            plainPassword: $request->password,
        ));

        return new JsonResponse(['id' => $id], 201);
    }
}
