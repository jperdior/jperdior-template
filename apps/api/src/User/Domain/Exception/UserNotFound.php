<?php

declare(strict_types=1);

namespace App\User\Domain\Exception;

use DomainException;

final class UserNotFound extends DomainException
{
    public static function withId(string $id): self
    {
        return new self(\sprintf('User %s not found.', $id));
    }

    public static function withEmail(string $email): self
    {
        return new self(\sprintf('No user found for email %s.', $email));
    }
}
