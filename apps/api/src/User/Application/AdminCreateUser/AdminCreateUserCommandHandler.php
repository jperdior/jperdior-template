<?php

declare(strict_types=1);

namespace App\User\Application\AdminCreateUser;

use Jperdior\SharedKernel\Domain\Bus\Command\CommandHandler;

final readonly class AdminCreateUserCommandHandler implements CommandHandler
{
    public function __construct(private AdminCreateUserUseCase $useCase)
    {
    }

    public function __invoke(AdminCreateUserCommand $command): void
    {
        ($this->useCase)($command);
    }
}
