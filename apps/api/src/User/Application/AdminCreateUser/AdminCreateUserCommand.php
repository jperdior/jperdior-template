<?php

declare(strict_types=1);

namespace App\User\Application\AdminCreateUser;

use Jperdior\SharedKernel\Domain\Bus\Command\Command;

final readonly class AdminCreateUserCommand implements Command
{
    public function __construct(
        public string $id,
        public string $email,
        public string $plainPassword,
    ) {
    }
}
