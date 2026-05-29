<?php

declare(strict_types=1);

namespace App\Note\Domain\Event;

use Jperdior\SharedKernel\Domain\Bus\Event\DomainEvent;

final class NoteCreated extends DomainEvent
{
    public function __construct(
        string $aggregateId,
        public readonly string $ownerId,
        public readonly string $title,
        public readonly string $body,
        public readonly string $createdAt,
        ?string $eventId = null,
        ?string $occurredOn = null,
    ) {
        parent::__construct($aggregateId, $eventId, $occurredOn);
    }

    public static function eventName(): string
    {
        return 'note.note.created';
    }

    public static function fromPrimitives(string $aggregateId, array $body, string $eventId, string $occurredOn): self
    {
        return new self(
            aggregateId: $aggregateId,
            ownerId: (string) $body['ownerId'],
            title: (string) $body['title'],
            body: (string) $body['body'],
            createdAt: (string) $body['createdAt'],
            eventId: $eventId,
            occurredOn: $occurredOn,
        );
    }

    public function toPrimitives(): array
    {
        return [
            'ownerId'   => $this->ownerId,
            'title'     => $this->title,
            'body'      => $this->body,
            'createdAt' => $this->createdAt,
        ];
    }
}
