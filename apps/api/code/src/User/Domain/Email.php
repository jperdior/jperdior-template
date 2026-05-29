<?php

declare(strict_types=1);

namespace App\User\Domain;

use Jperdior\SharedKernel\Domain\ValueObject\StringValueObject;

final readonly class Email extends StringValueObject
{
    public function __construct(string $value)
    {
        $normalized = strtolower(trim($value));
        if (!filter_var($normalized, FILTER_VALIDATE_EMAIL)) {
            throw new \InvalidArgumentException(sprintf('Invalid email: %s', $value));
        }
        parent::__construct($normalized);
    }
}
