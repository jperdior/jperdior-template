<?php

declare(strict_types=1);

namespace App\Note\Application\Query\ListNotes;

use App\Note\Application\Query\GetNote\NoteResponse;
use App\Note\Domain\NoteRepository;
use App\Note\Domain\OwnerId;
use Jperdior\SharedKernel\Domain\Bus\Query\QueryHandler;

final readonly class ListNotesQueryHandler implements QueryHandler
{
    public function __construct(private NoteRepository $notes)
    {
    }

    public function __invoke(ListNotesQuery $query): NoteListResponse
    {
        $owner = OwnerId::fromString($query->ownerId);
        $rows  = $this->notes->findByOwner($owner, max(1, min(100, $query->limit)), max(0, $query->offset));

        $items = array_map(fn ($note) => new NoteResponse(
            id: $note->id()->value,
            ownerId: $note->ownerId()->value,
            title: $note->title()->value,
            body: $note->body()->value,
            createdAt: $note->createdAt()->format(\DateTimeInterface::ATOM),
            updatedAt: $note->updatedAt()->format(\DateTimeInterface::ATOM),
        ), $rows);

        return new NoteListResponse($items, count($items));
    }
}
