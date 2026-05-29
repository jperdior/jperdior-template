<?php

declare(strict_types=1);

namespace App\Note\Domain;

use App\Note\Domain\Event\NoteCreated;
use App\Note\Domain\Event\NoteDeleted;
use App\Note\Domain\Event\NoteUpdated;
use App\Note\Domain\Exception\NoteNotOwnedByUser;
use Jperdior\SharedKernel\Domain\Aggregate\AggregateRoot;

final class Note extends AggregateRoot
{
    private function __construct(
        private readonly NoteId $id,
        private readonly OwnerId $ownerId,
        private NoteTitle $title,
        private NoteBody $body,
        private readonly \DateTimeImmutable $createdAt,
        private \DateTimeImmutable $updatedAt,
    ) {
    }

    public static function create(
        NoteId $id,
        OwnerId $ownerId,
        NoteTitle $title,
        NoteBody $body,
        \DateTimeImmutable $now,
    ): self {
        $note = new self($id, $ownerId, $title, $body, $now, $now);
        $note->record(new NoteCreated(
            $id->value,
            $ownerId->value,
            $title->value,
            $body->value,
            $now->format(\DateTimeInterface::ATOM),
        ));

        return $note;
    }

    public static function rehydrate(
        NoteId $id,
        OwnerId $ownerId,
        NoteTitle $title,
        NoteBody $body,
        \DateTimeImmutable $createdAt,
        \DateTimeImmutable $updatedAt,
    ): self {
        return new self($id, $ownerId, $title, $body, $createdAt, $updatedAt);
    }

    public function update(OwnerId $editor, NoteTitle $title, NoteBody $body, \DateTimeImmutable $now): void
    {
        $this->ensureOwner($editor);
        $this->title     = $title;
        $this->body      = $body;
        $this->updatedAt = $now;
        $this->record(new NoteUpdated(
            $this->id->value,
            $this->ownerId->value,
            $title->value,
            $body->value,
            $now->format(\DateTimeInterface::ATOM),
        ));
    }

    public function delete(OwnerId $editor, \DateTimeImmutable $now): void
    {
        $this->ensureOwner($editor);
        $this->record(new NoteDeleted(
            $this->id->value,
            $this->ownerId->value,
            $now->format(\DateTimeInterface::ATOM),
        ));
    }

    public function id(): NoteId
    {
        return $this->id;
    }

    public function ownerId(): OwnerId
    {
        return $this->ownerId;
    }

    public function title(): NoteTitle
    {
        return $this->title;
    }

    public function body(): NoteBody
    {
        return $this->body;
    }

    public function createdAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function updatedAt(): \DateTimeImmutable
    {
        return $this->updatedAt;
    }

    private function ensureOwner(OwnerId $editor): void
    {
        if (!$editor->equals($this->ownerId)) {
            throw new NoteNotOwnedByUser($this->id->value, $editor->value);
        }
    }
}
