<?php

declare(strict_types=1);

namespace App\User\Domain\Exception;

use DomainException;

final class PasswordRecoveryTokenAlreadyUsed extends DomainException
{
    public function __construct()
    {
        parent::__construct('Password recovery token has already been used.');
    }
}
