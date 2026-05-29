<?php

declare(strict_types=1);

namespace Jperdior\SharedKernel\Domain\Aggregate;

use Jperdior\SharedKernel\Domain\Bus\Event\DomainEvent;

abstract class AggregateRoot
{
    /** @var list<DomainEvent> */
    private array $domainEvents = [];

    /** @return list<DomainEvent> */
    final public function pullDomainEvents(): array
    {
        $events = $this->domainEvents;
        $this->domainEvents = [];

        return $events;
    }

    final protected function record(DomainEvent $event): void
    {
        $this->domainEvents[] = $event;
    }
}
