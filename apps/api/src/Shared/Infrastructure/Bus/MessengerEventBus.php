<?php

declare(strict_types=1);

namespace App\Shared\Infrastructure\Bus;

use Jperdior\SharedKernel\Domain\Bus\Event\DomainEvent;
use Jperdior\SharedKernel\Domain\Bus\Event\EventBus;
use Symfony\Component\Messenger\MessageBusInterface;

final readonly class MessengerEventBus implements EventBus
{
    public function __construct(private MessageBusInterface $bus)
    {
    }

    public function publish(DomainEvent ...$events): void
    {
        foreach ($events as $event) {
            $this->bus->dispatch($event);
        }
    }
}
