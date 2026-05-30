<?php

declare(strict_types=1);

namespace App\User\Application\Command\SelfResetPassword;

use App\User\Domain\Exception\UserNotFound;
use App\User\Domain\PasswordHasherInterface;
use App\User\Domain\PlainPassword;
use App\User\Domain\UserId;
use App\User\Domain\UserRepository;

final class SelfResetPasswordUseCase
{
    public function __construct(
        private readonly UserRepository $users,
        private readonly PasswordHasherInterface $hasher,
    ) {
    }

    public function __invoke(SelfResetPasswordCommand $command): void
    {
        $user = $this->users->findById(UserId::fromString($command->userId));

        if (null === $user) {
            throw UserNotFound::withId($command->userId);
        }

        $user->changePassword($this->hasher->hash(new PlainPassword($command->newPassword)));

        $this->users->save($user);
    }
}
