<?php

declare(strict_types=1);

namespace App\Note\Presentation\Http;

use App\Note\Application\Query\GetNote\GetNoteQuery;
use App\Note\Application\Query\GetNote\NoteResponse;
use Jperdior\SharedKernel\Domain\Bus\Query\QueryBus;
use OpenApi\Attributes as OA;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/api/notes/{id}', methods: ['GET'], requirements: ['id' => '[0-9a-fA-F-]{36}'])]
#[IsGranted('IS_AUTHENTICATED_FULLY')]
final class GetNoteController
{
    public function __construct(
        private readonly QueryBus $queryBus,
        private readonly CurrentUserId $currentUserId,
    ) {
    }

    #[OA\Tag(name: 'Notes')]
    #[OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'string', format: 'uuid'))]
    #[OA\Response(
        response: 200,
        description: 'Single note.',
        content: new OA\JsonContent(properties: [
            new OA\Property(property: 'id',        type: 'string', format: 'uuid'),
            new OA\Property(property: 'ownerId',   type: 'string', format: 'uuid'),
            new OA\Property(property: 'title',     type: 'string'),
            new OA\Property(property: 'body',      type: 'string'),
            new OA\Property(property: 'createdAt', type: 'string', format: 'date-time'),
            new OA\Property(property: 'updatedAt', type: 'string', format: 'date-time'),
        ]),
    )]
    #[OA\Response(response: 404, description: 'Note not found.')]
    public function __invoke(string $id): JsonResponse
    {
        /** @var NoteResponse $response */
        $response = $this->queryBus->ask(new GetNoteQuery($id, $this->currentUserId->value()));

        return new JsonResponse([
            'id'        => $response->id,
            'ownerId'   => $response->ownerId,
            'title'     => $response->title,
            'body'      => $response->body,
            'createdAt' => $response->createdAt,
            'updatedAt' => $response->updatedAt,
        ]);
    }
}
