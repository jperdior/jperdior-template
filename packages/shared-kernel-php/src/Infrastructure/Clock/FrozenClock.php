<?php

declare(strict_types=1);

namespace Jperdior\SharedKernel\Infrastructure\Clock;

use Jperdior\SharedKernel\Domain\Clock\ClockInterface;

final class FrozenClock implements ClockInterface
{
    public function __construct(private \DateTimeImmutable $now)
    {
    }

    public function now(): \DateTimeImmutable
    {
        return $this->now;
    }

    public function travel(\DateInterval $interval): void
    {
        $this->now = $this->now->add($interval);
    }
}
