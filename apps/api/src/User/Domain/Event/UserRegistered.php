<?php

declare(strict_types=1);

namespace App\User\Domain\Event;

use Jperdior\SharedKernel\Domain\Bus\Event\DomainEvent;

final class UserRegistered extends DomainEvent
{
    /**
     * @param list<string> $roles
     */
    public function __construct(
        string $aggregateId,
        public readonly string $email,
        public readonly array $roles,
        public readonly string $registeredAt,
        ?string $eventId = null,
        ?string $occurredOn = null,
    ) {
        parent::__construct($aggregateId, $eventId, $occurredOn);
    }

    public static function eventName(): string
    {
        return 'user.account.registered';
    }

    public static function fromPrimitives(
        string $aggregateId,
        array $body,
        string $eventId,
        string $occurredOn,
    ): self {
        return new self(
            aggregateId: $aggregateId,
            email: (string) $body['email'],
            roles: array_values(array_map('strval', (array) $body['roles'])),
            registeredAt: (string) $body['registeredAt'],
            eventId: $eventId,
            occurredOn: $occurredOn,
        );
    }

    public function toPrimitives(): array
    {
        return [
            'email' => $this->email,
            'roles' => $this->roles,
            'registeredAt' => $this->registeredAt,
        ];
    }
}
