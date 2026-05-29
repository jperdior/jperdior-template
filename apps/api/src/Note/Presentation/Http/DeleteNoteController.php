<?php

declare(strict_types=1);

namespace App\Note\Presentation\Http;

use App\Note\Application\Command\DeleteNote\DeleteNoteCommand;
use Jperdior\SharedKernel\Domain\Bus\Command\CommandBus;
use OpenApi\Attributes as OA;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/api/notes/{id}', methods: ['DELETE'], requirements: ['id' => '[0-9a-fA-F-]{36}'])]
#[IsGranted('IS_AUTHENTICATED_FULLY')]
final class DeleteNoteController
{
    public function __construct(
        private readonly CommandBus $commandBus,
        private readonly CurrentUserId $currentUserId,
    ) {
    }

    #[OA\Tag(name: 'Notes')]
    #[OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'string', format: 'uuid'))]
    #[OA\Response(response: 204, description: 'Note deleted.')]
    #[OA\Response(response: 404, description: 'Note not found.')]
    public function __invoke(string $id): JsonResponse
    {
        $this->commandBus->dispatch(new DeleteNoteCommand(
            id: $id,
            editorId: $this->currentUserId->value(),
        ));

        return new JsonResponse(null, 204);
    }
}
