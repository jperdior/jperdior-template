<?php

declare(strict_types=1);

namespace App\User\Application\RestoreUser;

use Jperdior\SharedKernel\Domain\Bus\Command\CommandHandler;

final readonly class RestoreUserCommandHandler implements CommandHandler
{
    public function __construct(private RestoreUserUseCase $useCase)
    {
    }

    public function __invoke(RestoreUserCommand $command): void
    {
        ($this->useCase)($command);
    }
}
