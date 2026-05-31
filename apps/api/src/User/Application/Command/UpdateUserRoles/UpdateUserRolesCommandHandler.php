<?php

declare(strict_types=1);

namespace App\User\Application\Command\UpdateUserRoles;

use Jperdior\SharedKernel\Domain\Bus\Command\CommandHandler;

final readonly class UpdateUserRolesCommandHandler implements CommandHandler
{
    public function __construct(private UpdateUserRolesUseCase $useCase)
    {
    }

    public function __invoke(UpdateUserRolesCommand $command): void
    {
        ($this->useCase)($command);
    }
}
