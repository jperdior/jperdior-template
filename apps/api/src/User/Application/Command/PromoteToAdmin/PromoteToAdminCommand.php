<?php

declare(strict_types=1);

namespace App\User\Application\Command\PromoteToAdmin;

use Jperdior\SharedKernel\Domain\Bus\Command\Command;

final readonly class PromoteToAdminCommand implements Command
{
    public function __construct(public string $email)
    {
    }
}
