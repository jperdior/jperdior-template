<?php

declare(strict_types=1);

namespace App\User\Application\Command\EnsureAdmin;

use Jperdior\SharedKernel\Domain\Bus\Command\Command;

final readonly class EnsureAdminCommand implements Command
{
    public function __construct(
        public string $email,
        public string $plainPassword,
    ) {
    }
}
