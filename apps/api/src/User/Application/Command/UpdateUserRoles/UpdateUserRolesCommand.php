<?php

declare(strict_types=1);

namespace App\User\Application\Command\UpdateUserRoles;

use Jperdior\SharedKernel\Domain\Bus\Command\Command;

final readonly class UpdateUserRolesCommand implements Command
{
    /** @param list<string> $roles */
    public function __construct(
        public string $userId,
        public array $roles,
    ) {
    }
}
