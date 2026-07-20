<?php

declare(strict_types=1);

namespace App\User\Application\SoftDeleteUser;

use App\User\Domain\Exception\CannotDeleteSelf;
use App\User\Domain\Exception\UserNotFound;
use App\User\Domain\UserId;
use App\User\Domain\UserRepository;
use Jperdior\SharedKernel\Domain\Clock\ClockInterface;

final class SoftDeleteUserUseCase
{
    public function __construct(
        private readonly UserRepository $users,
        private readonly ClockInterface $clock,
    ) {
    }

    public function __invoke(SoftDeleteUserCommand $command): void
    {
        if ($command->userId === $command->requestingAdminId) {
            throw CannotDeleteSelf::create();
        }

        $user = $this->users->findById(UserId::fromString($command->userId));

        if (null === $user) {
            throw UserNotFound::withId($command->userId);
        }

        $user->softDelete($this->clock->now());

        $this->users->save($user);
    }
}
