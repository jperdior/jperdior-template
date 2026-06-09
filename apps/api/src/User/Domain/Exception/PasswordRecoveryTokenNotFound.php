<?php

declare(strict_types=1);

namespace App\User\Domain\Exception;

use DomainException;

final class PasswordRecoveryTokenNotFound extends DomainException
{
    public static function create(): self
    {
        return new self('Password recovery token not found.');
    }
}
