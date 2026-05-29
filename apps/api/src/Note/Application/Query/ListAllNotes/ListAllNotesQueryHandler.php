<?php

declare(strict_types=1);

namespace App\Note\Application\Query\ListAllNotes;

use App\Note\Application\Query\GetNote\NoteResponse;
use App\Note\Application\Query\ListNotes\NoteListResponse;
use App\Note\Domain\NoteRepository;
use Jperdior\SharedKernel\Domain\Bus\Query\QueryHandler;

final readonly class ListAllNotesQueryHandler implements QueryHandler
{
    public function __construct(private NoteRepository $notes)
    {
    }

    public function __invoke(ListAllNotesQuery $query): NoteListResponse
    {
        $rows  = $this->notes->findAll(max(1, min(100, $query->limit)), max(0, $query->offset));
        $total = $this->notes->countAll();

        $items = array_map(fn ($note) => new NoteResponse(
            id: $note->id()->value,
            ownerId: $note->ownerId()->value,
            title: $note->title()->value,
            body: $note->body()->value,
            createdAt: $note->createdAt()->format(\DateTimeInterface::ATOM),
            updatedAt: $note->updatedAt()->format(\DateTimeInterface::ATOM),
        ), $rows);

        return new NoteListResponse($items, $total);
    }
}
