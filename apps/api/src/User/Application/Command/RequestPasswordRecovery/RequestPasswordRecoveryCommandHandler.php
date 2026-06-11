<?php

declare(strict_types=1);

namespace App\User\Application\Command\RequestPasswordRecovery;

use Jperdior\SharedKernel\Domain\Bus\Command\CommandHandler;

final readonly class RequestPasswordRecoveryCommandHandler implements CommandHandler
{
    public function __construct(private RequestPasswordRecoveryUseCase $useCase)
    {
    }

    public function __invoke(RequestPasswordRecoveryCommand $command): void
    {
        ($this->useCase)($command);
    }
}
