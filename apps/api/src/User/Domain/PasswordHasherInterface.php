<?php

declare(strict_types=1);

namespace App\User\Domain;

interface PasswordHasherInterface
{
    public function hash(PlainPassword $plain): HashedPassword;

    public function verify(HashedPassword $hashed, PlainPassword $plain): bool;
}
