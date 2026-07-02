<?php

declare(strict_types=1);

namespace App\Tests\Doubles;

use App\User\Domain\HashedPassword;
use App\User\Domain\PasswordHasherInterface;
use App\User\Domain\PlainPassword;

/** Deterministic, reversible "hash" so tests can assert password changes without Argon2 cost. */
final class FakePasswordHasher implements PasswordHasherInterface
{
    public function hash(PlainPassword $plain): HashedPassword
    {
        return new HashedPassword('hashed::'.$plain->value);
    }

    public function verify(HashedPassword $hashed, PlainPassword $plain): bool
    {
        return $hashed->value === 'hashed::'.$plain->value;
    }
}
