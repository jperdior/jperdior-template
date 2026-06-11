<?php

declare(strict_types=1);

namespace App\User\Application\Command\ResetPasswordWithToken;

use Jperdior\SharedKernel\Domain\Bus\Command\Command;

final readonly class ResetPasswordWithTokenCommand implements Command
{
    public function __construct(
        public string $token,
        public string $newPassword,
    ) {
    }
}
