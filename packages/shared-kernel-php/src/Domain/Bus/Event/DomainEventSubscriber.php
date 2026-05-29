<?php

declare(strict_types=1);

namespace Jperdior\SharedKernel\Domain\Bus\Event;

interface DomainEventSubscriber
{
    /** @return array<class-string<DomainEvent>> */
    public static function subscribedTo(): array;
}
