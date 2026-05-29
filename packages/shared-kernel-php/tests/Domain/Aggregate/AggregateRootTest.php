<?php

declare(strict_types=1);

namespace Jperdior\SharedKernel\Tests\Domain\Aggregate;

use Jperdior\SharedKernel\Domain\Aggregate\AggregateRoot;
use Jperdior\SharedKernel\Domain\Bus\Event\DomainEvent;
use PHPUnit\Framework\TestCase;

final class AggregateRootTest extends TestCase
{
    public function testItStartsWithNoPendingEvents(): void
    {
        $aggregate = new class extends AggregateRoot {};

        self::assertSame([], $aggregate->pullDomainEvents());
    }

    public function testItRecordsAndDrainsEvents(): void
    {
        $aggregate = new class extends AggregateRoot {
            public function doSomething(DomainEvent $e): void
            {
                $this->record($e);
            }
        };

        $event = new class('agg-1') extends DomainEvent {
            public static function eventName(): string
            {
                return 'test.event';
            }

            public static function fromPrimitives(string $aggregateId, array $body, string $eventId, string $occurredOn): self
            {
                return new self($aggregateId, $eventId, $occurredOn);
            }

            public function toPrimitives(): array
            {
                return [];
            }
        };

        $aggregate->doSomething($event);

        $drained = $aggregate->pullDomainEvents();
        self::assertCount(1, $drained);
        self::assertSame($event, $drained[0]);
        self::assertSame([], $aggregate->pullDomainEvents(), 'second pull must be empty');
    }
}
