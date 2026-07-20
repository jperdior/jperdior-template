<?php

declare(strict_types=1);

namespace App\User\Presentation\Http;

use App\User\Application\AdminCreateUser\AdminCreateUserCommand;
use App\User\Presentation\Http\Dto\AdminCreateUserRequest;
use Jperdior\SharedKernel\Domain\Bus\Command\CommandBus;
use OpenApi\Attributes as OA;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\Uid\Uuid;

#[Route('/api/admin/users', name: 'api_admin_create_user', methods: ['POST'])]
#[IsGranted('ROLE_ADMIN')]
final class AdminCreateUserController
{
    public function __construct(private readonly CommandBus $commandBus)
    {
    }

    #[OA\Tag(name: 'Admin')]
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
        description: 'User created by admin.',
        content: new OA\JsonContent(properties: [
            new OA\Property(property: 'id', type: 'string', format: 'uuid'),
        ]),
    )]
    #[OA\Response(response: 409, description: 'Email already in use.')]
    public function __invoke(#[MapRequestPayload] AdminCreateUserRequest $request): JsonResponse
    {
        $id = Uuid::v4()->toRfc4122();
        $this->commandBus->dispatch(new AdminCreateUserCommand(
            id: $id,
            email: $request->email,
            plainPassword: $request->password,
        ));

        return new JsonResponse(['id' => $id], 201);
    }
}
