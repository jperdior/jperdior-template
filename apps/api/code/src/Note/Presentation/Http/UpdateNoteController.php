<?php

declare(strict_types=1);

namespace App\Note\Presentation\Http;

use App\Note\Application\Command\UpdateNote\UpdateNoteCommand;
use App\Note\Presentation\Http\Dto\UpdateNoteRequest;
use Jperdior\SharedKernel\Domain\Bus\Command\CommandBus;
use OpenApi\Attributes as OA;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/api/notes/{id}', methods: ['PATCH'], requirements: ['id' => '[0-9a-fA-F-]{36}'])]
#[IsGranted('IS_AUTHENTICATED_FULLY')]
final class UpdateNoteController
{
    public function __construct(
        private readonly CommandBus $commandBus,
        private readonly CurrentUserId $currentUserId,
    ) {
    }

    #[OA\Tag(name: 'Notes')]
    #[OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'string', format: 'uuid'))]
    #[OA\RequestBody(
        required: true,
        content: new OA\JsonContent(
            required: ['title', 'body'],
            properties: [
                new OA\Property(property: 'title', type: 'string'),
                new OA\Property(property: 'body',  type: 'string'),
            ],
        ),
    )]
    #[OA\Response(response: 204, description: 'Note updated.')]
    #[OA\Response(response: 404, description: 'Note not found.')]
    public function __invoke(string $id, #[MapRequestPayload] UpdateNoteRequest $request): JsonResponse
    {
        $this->commandBus->dispatch(new UpdateNoteCommand(
            id: $id,
            editorId: $this->currentUserId->value(),
            title: $request->title,
            body: $request->body,
        ));

        return new JsonResponse(null, 204);
    }
}
