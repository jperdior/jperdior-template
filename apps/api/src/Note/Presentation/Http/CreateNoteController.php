<?php

declare(strict_types=1);

namespace App\Note\Presentation\Http;

use App\Note\Application\Command\CreateNote\CreateNoteCommand;
use App\Note\Presentation\Http\Dto\CreateNoteRequest;
use Jperdior\SharedKernel\Domain\Bus\Command\CommandBus;
use OpenApi\Attributes as OA;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\Uid\Uuid;

#[Route('/api/notes', methods: ['POST'])]
#[IsGranted('IS_AUTHENTICATED_FULLY')]
final class CreateNoteController
{
    public function __construct(
        private readonly CommandBus $commandBus,
        private readonly CurrentUserId $currentUserId,
    ) {
    }

    #[OA\Tag(name: 'Notes')]
    #[OA\RequestBody(
        required: true,
        content: new OA\JsonContent(
            required: ['title', 'body'],
            properties: [
                new OA\Property(property: 'title', type: 'string', maxLength: 200),
                new OA\Property(property: 'body',  type: 'string', maxLength: 10000),
            ],
        ),
    )]
    #[OA\Response(
        response: 201,
        description: 'Note created.',
        content: new OA\JsonContent(properties: [
            new OA\Property(property: 'id', type: 'string', format: 'uuid'),
        ]),
    )]
    public function __invoke(#[MapRequestPayload] CreateNoteRequest $request): JsonResponse
    {
        $id = Uuid::v4()->toRfc4122();
        $this->commandBus->dispatch(new CreateNoteCommand(
            id: $id,
            ownerId: $this->currentUserId->value(),
            title: $request->title,
            body: $request->body,
        ));

        return new JsonResponse(['id' => $id], 201);
    }
}
