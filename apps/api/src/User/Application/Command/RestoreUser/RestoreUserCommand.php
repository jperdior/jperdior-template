<?php

declare(strict_types=1);

namespace App\User\Application\Command\RestoreUser;

use Jperdior\SharedKernel\Domain\Bus\Command\Command;

final readonly class RestoreUserCommand implements Command
{
    public function __construct(public string $userId)
    {
    }
}
