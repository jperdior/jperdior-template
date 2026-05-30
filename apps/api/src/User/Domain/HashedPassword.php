<?php

declare(strict_types=1);

namespace App\User\Domain;

use InvalidArgumentException;
use Jperdior\SharedKernel\Domain\ValueObject\StringValueObject;

final readonly class HashedPassword extends StringValueObject
{
    public function __construct(string $value)
    {
        if ('' === $value) {
            throw new InvalidArgumentException('Password hash cannot be empty.');
        }
        parent::__construct($value);
    }
}
