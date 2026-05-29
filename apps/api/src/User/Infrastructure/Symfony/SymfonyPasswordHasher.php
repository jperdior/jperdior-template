<?php

declare(strict_types=1);

namespace App\User\Infrastructure\Symfony;

use App\User\Domain\HashedPassword;
use App\User\Domain\PasswordHasherInterface;
use App\User\Domain\PlainPassword;
use App\User\Infrastructure\Symfony\Security\SecurityUser;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

final readonly class SymfonyPasswordHasher implements PasswordHasherInterface
{
    public function __construct(private UserPasswordHasherInterface $hasher)
    {
    }

    public function hash(PlainPassword $plain): HashedPassword
    {
        $user = SecurityUser::placeholder();

        return new HashedPassword($this->hasher->hashPassword($user, $plain->value));
    }

    public function verify(HashedPassword $hashed, PlainPassword $plain): bool
    {
        $user = SecurityUser::placeholderWithHash($hashed->value);

        return $this->hasher->isPasswordValid($user, $plain->value);
    }
}
