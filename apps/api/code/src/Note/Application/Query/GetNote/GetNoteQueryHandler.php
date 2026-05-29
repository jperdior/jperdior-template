<?php

declare(strict_types=1);

namespace App\Note\Application\Query\GetNote;

use App\Note\Domain\Exception\NoteNotFound;
use App\Note\Domain\Exception\NoteNotOwnedByUser;
use App\Note\Domain\NoteId;
use App\Note\Domain\NoteRepository;
use Jperdior\SharedKernel\Domain\Bus\Query\QueryHandler;

final readonly class GetNoteQueryHandler implements QueryHandler
{
    public function __construct(private NoteRepository $notes)
    {
    }

    public function __invoke(GetNoteQuery $query): NoteResponse
    {
        $note = $this->notes->findById(NoteId::fromString($query->id))
            ?? throw NoteNotFound::withId($query->id);

        if ($note->ownerId()->value !== $query->requesterId) {
            throw new NoteNotOwnedByUser($note->id()->value, $query->requesterId);
        }

        return new NoteResponse(
            id: $note->id()->value,
            ownerId: $note->ownerId()->value,
            title: $note->title()->value,
            body: $note->body()->value,
            createdAt: $note->createdAt()->format(\DateTimeInterface::ATOM),
            updatedAt: $note->updatedAt()->format(\DateTimeInterface::ATOM),
        );
    }
}
