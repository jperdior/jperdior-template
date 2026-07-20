<?php

declare(strict_types=1);

namespace App\User\Application\SignUp;

use Jperdior\SharedKernel\Domain\Bus\Command\CommandHandler;

final readonly class SignUpCommandHandler implements CommandHandler
{
    public function __construct(private SignUpUseCase $useCase)
    {
    }

    public function __invoke(SignUpCommand $command): void
    {
        ($this->useCase)($command);
    }
}
