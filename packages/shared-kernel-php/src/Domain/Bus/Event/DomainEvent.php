<?php

declare(strict_types=1);

namespace Jperdior\SharedKernel\Domain\Bus\Event;

use Symfony\Component\Uid\Ulid;

abstract class DomainEvent
{
    private readonly string $eventId;
    private readonly string $occurredOn;

    public function __construct(
        public readonly string $aggregateId,
        ?string $eventId = null,
        ?string $occurredOn = null,
    ) {
        $this->eventId    = $eventId    ?? (new Ulid())->toRfc4122();
        $this->occurredOn = $occurredOn ?? (new \DateTimeImmutable())->format(\DateTimeInterface::ATOM);
    }

    abstract public static function eventName(): string;

    /**
     * @param array<string, mixed> $body
     */
    abstract public static function fromPrimitives(
        string $aggregateId,
        array $body,
        string $eventId,
        string $occurredOn,
    ): self;

    /** @return array<string, mixed> */
    abstract public function toPrimitives(): array;

    final public function eventId(): string
    {
        return $this->eventId;
    }

    final public function occurredOn(): string
    {
        return $this->occurredOn;
    }
}
