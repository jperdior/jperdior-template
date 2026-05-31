<?php

declare(strict_types=1);

namespace App\User\Application\Command\ForcePasswordReset;

use Jperdior\SharedKernel\Domain\Bus\Command\CommandHandler;

final readonly class ForcePasswordResetCommandHandler implements CommandHandler
{
    public function __construct(private ForcePasswordResetUseCase $useCase)
    {
    }

    public function __invoke(ForcePasswordResetCommand $command): void
    {
        ($this->useCase)($command);
    }
}
