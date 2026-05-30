<?php

declare(strict_types=1);

namespace App\Shared\Infrastructure\Bus;

use Jperdior\SharedKernel\Domain\Bus\Query\Query;
use Jperdior\SharedKernel\Domain\Bus\Query\QueryBus;
use Jperdior\SharedKernel\Domain\Bus\Query\QueryResponse;
use Symfony\Component\Messenger\Exception\HandlerFailedException;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Messenger\Stamp\HandledStamp;

final readonly class MessengerQueryBus implements QueryBus
{
    public function __construct(private MessageBusInterface $bus)
    {
    }

    public function ask(Query $query): ?QueryResponse
    {
        try {
            $envelope = $this->bus->dispatch($query);
        } catch (HandlerFailedException $e) {
            $previous = $e->getPrevious();
            if (null !== $previous) {
                throw $previous;
            }
            throw $e;
        }

        $stamp = $envelope->last(HandledStamp::class);
        if (null === $stamp) {
            return null;
        }

        /** @var QueryResponse|null $result */
        $result = $stamp->getResult();

        return $result;
    }
}
