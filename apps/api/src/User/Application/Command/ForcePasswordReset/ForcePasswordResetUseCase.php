<?php

declare(strict_types=1);

namespace App\User\Application\Command\ForcePasswordReset;

use App\User\Domain\Exception\UserNotFound;
use App\User\Domain\UserId;
use App\User\Domain\UserRepository;

final class ForcePasswordResetUseCase
{
    public function __construct(private readonly UserRepository $users)
    {
    }

    public function __invoke(ForcePasswordResetCommand $command): void
    {
        $user = $this->users->findById(UserId::fromString($command->userId));

        if (null === $user) {
            throw UserNotFound::withId($command->userId);
        }

        $user->forcePasswordReset();

        $this->users->save($user);
    }
}
