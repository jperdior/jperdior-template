<?php

declare(strict_types=1);

namespace App\User\Application\SignUp;

use Jperdior\SharedKernel\Domain\Bus\Command\Command;

final readonly class SignUpCommand implements Command
{
    public function __construct(
        public string $id,
        public string $email,
        public string $plainPassword,
    ) {
    }
}
