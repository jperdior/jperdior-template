<?php

declare(strict_types=1);

namespace Jperdior\SharedKernel\Infrastructure\Clock;

use Jperdior\SharedKernel\Domain\Clock\ClockInterface;

final readonly class SystemClock implements ClockInterface
{
    public function now(): \DateTimeImmutable
    {
        return new \DateTimeImmutable();
    }
}
