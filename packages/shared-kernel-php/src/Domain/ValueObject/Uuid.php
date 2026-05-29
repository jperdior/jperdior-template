<?php

declare(strict_types=1);

namespace Jperdior\SharedKernel\Domain\ValueObject;

use Symfony\Component\Uid\Uuid as SymfonyUuid;

abstract readonly class Uuid
{
    final public function __construct(public string $value)
    {
        if (!SymfonyUuid::isValid($value)) {
            throw new \InvalidArgumentException(sprintf('Invalid UUID: %s', $value));
        }
    }

    final public static function random(): static
    {
        return new static(SymfonyUuid::v4()->toRfc4122());
    }

    final public static function fromString(string $value): static
    {
        return new static($value);
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
