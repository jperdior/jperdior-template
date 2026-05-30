<?php

declare(strict_types=1);

namespace App\User\Application\Command\SelfResetPassword;

use Jperdior\SharedKernel\Domain\Bus\Command\Command;

final readonly class SelfResetPasswordCommand implements Command
{
    public function __construct(
        public string $userId,
        public string $newPassword,
    ) {
    }
}
