<?php

declare(strict_types=1);

namespace App\User\Application\Command\SoftDeleteUser;

use Jperdior\SharedKernel\Domain\Bus\Command\CommandHandler;

final readonly class SoftDeleteUserCommandHandler implements CommandHandler
{
    public function __construct(private SoftDeleteUserUseCase $useCase)
    {
    }

    public function __invoke(SoftDeleteUserCommand $command): void
    {
        ($this->useCase)($command);
    }
}
