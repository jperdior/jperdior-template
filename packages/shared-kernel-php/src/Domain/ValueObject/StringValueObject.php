<?php

declare(strict_types=1);

namespace Jperdior\SharedKernel\Domain\ValueObject;

abstract readonly class StringValueObject
{
    public function __construct(public string $value)
    {
    }

    final public function equals(self $other): bool
    {
        return $this::class === $other::class && $this->value === $other->value;
    }

    final public function __toString(): string
    {
        return $this->value;
    }
}
