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
    public function __construct(
        private readonly UserRepository $repository,
        private readonly PasswordHasherInterface $hasher,
    ) {
    }

    public function createOne(string $email = 'user@example.com', string $password = 'secretpass'): User
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
}
