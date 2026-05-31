<?php

declare(strict_types=1);

namespace App\User\Presentation\Http;

use App\User\Application\Query\GetCurrentUser\CurrentUserResponse;
use App\User\Application\Query\GetCurrentUser\GetCurrentUserQuery;
use Jperdior\SharedKernel\Domain\Bus\Query\QueryBus;
use OpenApi\Attributes as OA;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/api/me', name: 'api_user_me', methods: ['GET'])]
#[IsGranted('IS_AUTHENTICATED_FULLY')]
final class MeController
{
    public function __construct(
        private readonly QueryBus $queryBus,
        private readonly Security $security,
    ) {
    }

    #[OA\Tag(name: 'Auth')]
    #[OA\Response(
        response: 200,
        description: 'Currently authenticated user.',
        content: new OA\JsonContent(properties: [
            new OA\Property(property: 'id', type: 'string', format: 'uuid'),
            new OA\Property(property: 'email', type: 'string', format: 'email'),
            new OA\Property(property: 'roles', type: 'array', items: new OA\Items(type: 'string')),
            new OA\Property(property: 'createdAt', type: 'string', format: 'date-time'),
            new OA\Property(property: 'mustResetPassword', type: 'boolean'),
        ]),
    )]
    public function __invoke(): JsonResponse
    {
        $identifier = (string) $this->security->getUser()?->getUserIdentifier();

        /** @var CurrentUserResponse $response */
        $response = $this->queryBus->ask(new GetCurrentUserQuery($identifier));

        return new JsonResponse([
            'id' => $response->id,
            'email' => $response->email,
            'roles' => $response->roles,
            'createdAt' => $response->createdAt,
            'mustResetPassword' => $response->mustResetPassword,
        ]);
    }
}
