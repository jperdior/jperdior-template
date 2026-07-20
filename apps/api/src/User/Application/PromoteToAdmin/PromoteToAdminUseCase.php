<?php

declare(strict_types=1);

namespace App\User\Application\PromoteToAdmin;

use App\User\Domain\Email;
use App\User\Domain\Exception\UserNotFound;
use App\User\Domain\UserRepository;

final class PromoteToAdminUseCase
{
    public function __construct(private readonly UserRepository $users)
    {
    }

    public function __invoke(PromoteToAdminCommand $command): void
    {
        $email = new Email($command->email);
        $user = $this->users->findByEmail($email);

        if (null === $user) {
            throw UserNotFound::withEmail($email->value);
        }

        $user->promoteToAdmin();
        $this->users->save($user);
    }
}
