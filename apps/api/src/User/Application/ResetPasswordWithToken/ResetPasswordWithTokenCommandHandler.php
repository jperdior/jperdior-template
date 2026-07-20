<?php

declare(strict_types=1);

namespace App\User\Application\ResetPasswordWithToken;

use Jperdior\SharedKernel\Domain\Bus\Command\CommandHandler;

final readonly class ResetPasswordWithTokenCommandHandler implements CommandHandler
{
    public function __construct(private ResetPasswordWithTokenUseCase $useCase)
    {
    }

    public function __invoke(ResetPasswordWithTokenCommand $command): void
    {
        ($this->useCase)($command);
    }
}
