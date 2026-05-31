<?php

declare(strict_types=1);

namespace App\User\Application\Command\SoftDeleteUser;

use Jperdior\SharedKernel\Domain\Bus\Command\Command;

final readonly class SoftDeleteUserCommand implements Command
{
    public function __construct(
        public string $userId,
        public string $requestingAdminId,
    ) {
    }
}
