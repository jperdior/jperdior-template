<?php

declare(strict_types=1);

namespace App\User\Application\Command\PromoteToAdmin;

use App\User\Domain\Email;
use App\User\Domain\Exception\UserNotFound;
use App\User\Domain\UserRepository;
use Jperdior\SharedKernel\Domain\Bus\Command\CommandHandler;

final readonly class PromoteToAdminCommandHandler implements CommandHandler
{
    public function __construct(private UserRepository $users)
    {
    }

    public function __invoke(PromoteToAdminCommand $command): void
    {
        $email = new Email($command->email);
        $user  = $this->users->findByEmail($email);

        if ($user === null) {
            throw UserNotFound::withEmail($email->value);
        }

        $user->promoteToAdmin();
        $this->users->save($user);
    }
}
