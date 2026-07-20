<?php

declare(strict_types=1);

namespace App\User\Application\ForcePasswordReset;

use Jperdior\SharedKernel\Domain\Bus\Command\Command;

final readonly class ForcePasswordResetCommand implements Command
{
    public function __construct(public string $userId)
    {
    }
}
