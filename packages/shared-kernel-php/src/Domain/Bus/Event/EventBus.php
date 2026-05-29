<?php

declare(strict_types=1);

namespace Jperdior\SharedKernel\Domain\Bus\Event;

interface EventBus
{
    public function publish(DomainEvent ...$events): void;
}
