<?php

declare(strict_types=1);

namespace Jperdior\SharedKernel\Domain\Clock;

interface ClockInterface
{
    public function now(): \DateTimeImmutable;
}
