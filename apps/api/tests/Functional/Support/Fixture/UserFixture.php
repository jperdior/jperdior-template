<?php

declare(strict_types=1);

namespace App\Tests\Functional\Support\Fixture;

use App\User\Domain\Email;
use App\User\Domain\PasswordHasherInterface;
use App\User\Domain\PlainPassword;
use App\User\Domain\Role;
use App\User\Domain\User;
use App\User\Domain\UserId;
use App\User\Domain\UserRepository;
use DateTimeImmutable;

final class UserFixture
{
    private const DEFAULT_PASSWORD = 'secretpass';

    public function __construct(
        private readonly UserRepository $repository,
        private readonly PasswordHasherInterface $hasher,
    ) {
    }

    public function createOne(string $email = 'user@example.com', string $password = self::DEFAULT_PASSWORD): User
    {
        $user = User::register(
            UserId::random(),
            new Email($email),
            $this->hasher->hash(new PlainPassword($password)),
            [Role::USER],
            new DateTimeImmutable(),
        );
        $this->repository->save($user);

        return $user;
    }

    public function createAdmin(string $email = 'admin@example.com', string $password = self::DEFAULT_PASSWORD): User
    {
        $user = User::register(
            UserId::random(),
            new Email($email),
            $this->hasher->hash(new PlainPassword($password)),
            [Role::USER, Role::ADMIN],
            new DateTimeImmutable(),
        );
        $this->repository->save($user);

        return $user;
    }
}
