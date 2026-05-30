<?php

declare(strict_types=1);

namespace App\User\Domain\Exception;

use DomainException;

final class CannotDeleteSelf extends DomainException
{
    public static function create(): self
    {
        return new self('An admin cannot delete their own account.');
    }
}
