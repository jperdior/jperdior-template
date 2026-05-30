<?php

declare(strict_types=1);

namespace App\User\Application\Command\PromoteToAdmin;

use Jperdior\SharedKernel\Domain\Bus\Command\CommandHandler;

final readonly class PromoteToAdminCommandHandler implements CommandHandler
{
    public function __construct(private PromoteToAdminUseCase $useCase)
    {
    }

    public function __invoke(PromoteToAdminCommand $command): void
    {
        ($this->useCase)($command);
    }
}
