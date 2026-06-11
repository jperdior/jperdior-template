<?php

declare(strict_types=1);

namespace App\User\Application\Command\RequestPasswordRecovery;

use Jperdior\SharedKernel\Domain\Bus\Command\Command;

final readonly class RequestPasswordRecoveryCommand implements Command
{
    public function __construct(
        public string $email,
    ) {
    }
}
