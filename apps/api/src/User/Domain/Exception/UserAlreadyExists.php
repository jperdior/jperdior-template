<?php

declare(strict_types=1);

namespace App\User\Domain\Exception;

use DomainException;

final class UserAlreadyExists extends DomainException
{
    public static function withEmail(string $email): self
    {
        return new self(\sprintf('A user with email %s already exists.', $email));
    }
}
