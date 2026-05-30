<?php

declare(strict_types=1);

namespace App\User\Domain;

use InvalidArgumentException;

final readonly class PlainPassword
{
    public function __construct(public string $value)
    {
        if (\strlen($value) < 8) {
            throw new InvalidArgumentException('Password must be at least 8 characters long.');
        }
        if (\strlen($value) > 4096) {
            throw new InvalidArgumentException('Password is too long.');
        }
    }
}
