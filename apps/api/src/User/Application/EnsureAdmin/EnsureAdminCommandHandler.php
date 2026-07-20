<?php

declare(strict_types=1);

namespace App\User\Application\EnsureAdmin;

use Jperdior\SharedKernel\Domain\Bus\Command\CommandHandler;

final readonly class EnsureAdminCommandHandler implements CommandHandler
{
    public function __construct(private EnsureAdminUseCase $useCase)
    {
    }

    public function __invoke(EnsureAdminCommand $command): void
    {
        ($this->useCase)($command);
    }
}
