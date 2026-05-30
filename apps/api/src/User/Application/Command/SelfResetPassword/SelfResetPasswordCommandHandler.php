<?php

declare(strict_types=1);

namespace App\User\Application\Command\SelfResetPassword;

use Jperdior\SharedKernel\Domain\Bus\Command\CommandHandler;

final readonly class SelfResetPasswordCommandHandler implements CommandHandler
{
    public function __construct(private SelfResetPasswordUseCase $useCase)
    {
    }

    public function __invoke(SelfResetPasswordCommand $command): void
    {
        ($this->useCase)($command);
    }
}
