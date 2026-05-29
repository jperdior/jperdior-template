<?php

declare(strict_types=1);

namespace App\Note\Domain;

interface NoteRepository
{
    public function save(Note $note): void;

    public function remove(Note $note): void;

    public function findById(NoteId $id): ?Note;

    /** @return list<Note> */
    public function findByOwner(OwnerId $owner, int $limit, int $offset): array;

    /** @return list<Note> */
    public function findAll(int $limit, int $offset): array;

    public function countAll(): int;
}
