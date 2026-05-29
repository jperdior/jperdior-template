<?php

declare(strict_types=1);

namespace App\Shared\Infrastructure\Bus;

use Jperdior\SharedKernel\Domain\Bus\Command\Command;
use Jperdior\SharedKernel\Domain\Bus\Command\CommandBus;
use Symfony\Component\Messenger\Exception\HandlerFailedException;
use Symfony\Component\Messenger\MessageBusInterface;

final readonly class MessengerCommandBus implements CommandBus
{
    public function __construct(private MessageBusInterface $bus)
    {
    }

    public function dispatch(Command $command): void
    {
        try {
            $this->bus->dispatch($command);
        } catch (HandlerFailedException $e) {
            $previous = $e->getPrevious();
            if ($previous !== null) {
                throw $previous;
            }
            throw $e;
        }
    }
}
