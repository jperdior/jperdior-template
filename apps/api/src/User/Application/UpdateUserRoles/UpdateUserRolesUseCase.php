<?php

declare(strict_types=1);

namespace App\User\Application\UpdateUserRoles;

use App\User\Domain\Exception\UserNotFound;
use App\User\Domain\Role;
use App\User\Domain\UserId;
use App\User\Domain\UserRepository;

final class UpdateUserRolesUseCase
{
    public function __construct(private readonly UserRepository $users)
    {
    }

    public function __invoke(UpdateUserRolesCommand $command): void
    {
        $user = $this->users->findById(UserId::fromString($command->userId));

        if (null === $user) {
            throw UserNotFound::withId($command->userId);
        }

        if (\in_array(Role::ADMIN->value, $command->roles, true)) {
            $user->promoteToAdmin();
        } else {
            $user->demoteFromAdmin();
        }

        $this->users->save($user);
    }
}
