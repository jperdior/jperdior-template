<?php

declare(strict_types=1);

namespace Jperdior\SharedKernel\Domain\ValueObject;

abstract readonly class DateTimeValueObject
{
    public function __construct(public \DateTimeImmutable $value)
    {
    }

    final public static function now(): static
    {
        return new static(new \DateTimeImmutable());
    }

    final public static function fromString(string $value): static
    {
        try {
            return new static(new \DateTimeImmutable($value));
        } catch (\Exception $e) {
            throw new \InvalidArgumentException(sprintf('Invalid datetime: %s', $value), previous: $e);
        }
    }

    final public function equals(self $other): bool
    {
        return $this::class === $other::class
            && $this->value->format(\DateTimeInterface::ATOM) === $other->value->format(\DateTimeInterface::ATOM);
    }

    final public function __toString(): string
    {
        return $this->value->format(\DateTimeInterface::ATOM);
    }
}
