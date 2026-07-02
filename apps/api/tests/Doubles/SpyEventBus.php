<?php

declare(strict_types=1);

namespace App\Tests\Doubles;

use Jperdior\SharedKernel\Domain\Bus\Event\DomainEvent;
use Jperdior\SharedKernel\Domain\Bus\Event\EventBus;

final class SpyEventBus implements EventBus
{
    /** @var list<DomainEvent> */
    public array $published = [];

    public function publish(DomainEvent ...$events): void
    {
        foreach ($events as $event) {
            $this->published[] = $event;
        }
    }
}
