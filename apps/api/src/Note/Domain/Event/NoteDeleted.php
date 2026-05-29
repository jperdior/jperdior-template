<?php

declare(strict_types=1);

namespace App\Note\Domain\Event;

use Jperdior\SharedKernel\Domain\Bus\Event\DomainEvent;

final class NoteDeleted extends DomainEvent
{
    public function __construct(
        string $aggregateId,
        public readonly string $ownerId,
        public readonly string $deletedAt,
        ?string $eventId = null,
        ?string $occurredOn = null,
    ) {
        parent::__construct($aggregateId, $eventId, $occurredOn);
    }

    public static function eventName(): string
    {
        return 'note.note.deleted';
    }

    public static function fromPrimitives(string $aggregateId, array $body, string $eventId, string $occurredOn): self
    {
        return new self(
            aggregateId: $aggregateId,
            ownerId: (string) $body['ownerId'],
            deletedAt: (string) $body['deletedAt'],
            eventId: $eventId,
            occurredOn: $occurredOn,
        );
    }

    public function toPrimitives(): array
    {
        return [
            'ownerId'   => $this->ownerId,
            'deletedAt' => $this->deletedAt,
        ];
    }
}
