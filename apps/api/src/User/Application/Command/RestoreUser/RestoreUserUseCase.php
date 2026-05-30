<?php

declare(strict_types=1);

namespace App\User\Application\Command\RestoreUser;

use App\User\Domain\Exception\UserNotFound;
use App\User\Domain\UserId;
use App\User\Domain\UserRepository;

final class RestoreUserUseCase
{
    public function __construct(private readonly UserRepository $users)
    {
    }

    public function __invoke(RestoreUserCommand $command): void
    {
        $user = $this->users->findByIdIncludingDeleted(UserId::fromString($command->userId));

        if (null === $user) {
            throw UserNotFound::withId($command->userId);
        }

        $user->restore();

        $this->users->save($user);
    }
}
