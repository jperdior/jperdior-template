<?php

declare(strict_types=1);

namespace App\Note\Presentation\Http;

use App\Note\Application\Query\ListAllNotes\ListAllNotesQuery;
use App\Note\Application\Query\ListNotes\NoteListResponse;
use Jperdior\SharedKernel\Domain\Bus\Query\QueryBus;
use OpenApi\Attributes as OA;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/api/admin/notes', methods: ['GET'])]
#[IsGranted('ROLE_ADMIN')]
final class AdminListNotesController
{
    public function __construct(private readonly QueryBus $queryBus)
    {
    }

    #[OA\Tag(name: 'Admin')]
    #[OA\Parameter(name: 'limit',  in: 'query', schema: new OA\Schema(type: 'integer', minimum: 1, maximum: 100, default: 50))]
    #[OA\Parameter(name: 'offset', in: 'query', schema: new OA\Schema(type: 'integer', minimum: 0, default: 0))]
    #[OA\Response(
        response: 200,
        description: 'Paginated list of every note across users.',
        content: new OA\JsonContent(properties: [
            new OA\Property(property: 'total', type: 'integer'),
            new OA\Property(property: 'notes', type: 'array', items: new OA\Items(properties: [
                new OA\Property(property: 'id',        type: 'string', format: 'uuid'),
                new OA\Property(property: 'ownerId',   type: 'string', format: 'uuid'),
                new OA\Property(property: 'title',     type: 'string'),
                new OA\Property(property: 'body',      type: 'string'),
                new OA\Property(property: 'createdAt', type: 'string', format: 'date-time'),
                new OA\Property(property: 'updatedAt', type: 'string', format: 'date-time'),
            ])),
        ]),
    )]
    #[OA\Response(response: 403, description: 'Caller is not an admin.')]
    public function __invoke(Request $request): JsonResponse
    {
        /** @var NoteListResponse $response */
        $response = $this->queryBus->ask(new ListAllNotesQuery(
            limit: (int) $request->query->get('limit', 50),
            offset: (int) $request->query->get('offset', 0),
        ));

        return new JsonResponse([
            'total' => $response->total,
            'notes' => array_map(fn ($n) => [
                'id'        => $n->id,
                'ownerId'   => $n->ownerId,
                'title'     => $n->title,
                'body'      => $n->body,
                'createdAt' => $n->createdAt,
                'updatedAt' => $n->updatedAt,
            ], $response->notes),
        ]);
    }
}
